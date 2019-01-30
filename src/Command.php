<?php
namespace Andrewlamers\LaravelAdvancedConsole;

use Andrewlamers\LaravelAdvancedConsole\Console\Formatter\OutputFormatter;
use Andrewlamers\LaravelAdvancedConsole\Exceptions\CommandHistoryOutputException;
use Andrewlamers\LaravelAdvancedConsole\Services\BenchmarkService;
use Andrewlamers\LaravelAdvancedConsole\Exceptions\InvalidServiceException;
use Andrewlamers\LaravelAdvancedConsole\Services\CommandHistoryService;
use Andrewlamers\LaravelAdvancedConsole\Services\LockingService;
use Andrewlamers\LaravelAdvancedConsole\Services\MetadataService;
use Andrewlamers\LaravelAdvancedConsole\Services\OutputService;
use Andrewlamers\LaravelAdvancedConsole\Services\ProfileService;
use Andrewlamers\LaravelAdvancedConsole\Services\Service;
use Illuminate\Console\Command as BaseCommand;
use Illuminate\Container\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Command
 *
 * @package Andrewlamers\LaravelAdvancedConsole
 * @property BenchmarkService $benchmark
 * @property MetadataService $metadata
 * @property CommandHistoryService $commandHistory
 * @property ProfileService $profile
 * @property LockingService $locking
 *
 */
abstract class Command extends BaseCommand
{
    /**
     * @var $enable_benchmark - Enable Benchmark of application time
     */
    public $enableBenchmark = true;

    /**
     * @var $enable_database_profile - Profiles executed SQL statements and outputs summary at the end of command
     */
    public $enableProfile = false;

    /**
     * @var bool $enableMetadata - Enable output of system metadata at the start of the command
     */
    public $enableMetadata = true;

    /**
     * @var bool $enableCommandHistory - Enables logging commands to the database with execution and environment details
     */
    public $enableCommandHistory = true;

    /**
     * @var bool $logOutput - $logOutput = true Will log all lines written to the command_history table. False will disable logging output.
     */
    public $logOutput = true;

    /**
     * @var bool $enableLocking - Enable locking, Command will not run if there is another one already running
     */
    public $enableLocking = false;

    /**
     * @var string $lineTemplate - Template for console lines
     */
    public $lineTemplate = '%level% %message%';

    /**
     * @var $memoryLimit - Memory limit for command - ini_set('memory_limit')
     */
    protected $memoryLimit = '256M';

    /**
     * @var $timeLimit - limit for command - set_time_limit
     */
    protected $timeLimit = 300;

    /**
     * @var array $services - Array of registered services
     */
    private $services = [];

    protected $outputFormatter;

    protected $enabled = true;

    protected $failed = false;

    private $memoryBuffer;

    /**
     * Command constructor.
     *
     * @throws InvalidServiceException
     */
    public function __construct() {

        $this->setLaravel(Container::getInstance());
        $this->registerService(OutputService::class);
        $this->registerService(BenchmarkService::class);
        $this->registerService(CommandHistoryService::class);
        $this->registerService(MetadataService::class);
        $this->registerService(LockingService::class);
        $this->registerService(ProfileService::class);
        $this->outputFormatter = new OutputFormatter(true);
        $this->outputFormatter->setCommand($this);

        parent::__construct();
    }

    public function getInput() {
        return $this->input;
    }

    protected function configure()
    {
        $this->executeCallbacks('configure', [], true);
    }

    /**
     * setMemoryBuffer
     * allocates 5MB of ram to clear on shutdown for handling exceptions and out
     * of memory errors.
     * @return void
     */
    private function setMemoryBuffer() {
        $this->memoryBuffer = str_repeat('*', 1024 * 1024 * 5);
    }

    /**
     * clearMemoryBuffer
     * Clears the memory buffer on shutdown to leave enough memory for handling
     * fatal errors and cleanup on out of memory errors
     */
    private function clearMemoryBuffer() {
        $this->memoryBuffer = null;
    }

    /**
     * Shutdown handler to clean up command history after command ends or throws exceptions
     * @throws CommandHistoryOutputException
     */
    public function onShutdown() {
        $this->clearMemoryBuffer();
        $error = error_get_last();

        if($error) {
            $this->commandHistory->getModel()->fail();
        }

        $this->commandHistory->saveOutput();

        if(!$this->commandHistory->setOutputCompressed()) {
            throw new CommandHistoryOutputException('Error setting output compression.');
        }
    }

    private function registerShutdownHandler() {
        $this->setMemoryBuffer();
        register_shutdown_function([$this, 'onShutdown']);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->registerShutdownHandler();

        parent::initialize($input, $output);

        $output->setFormatter($this->outputFormatter);
        $this->setMemoryLimit($this->memoryLimit);
        $this->setTimeLimit($this->timeLimit);
        $this->executeCallbacks('initialize', [$input, $output]);
        $this->beforeRun();
    }

    private function setTimeLimit($seconds) {
        set_time_limit($seconds);
    }

    private function setMemoryLimit($limit) {
        ini_set('memory_limit', $limit);
    }

    public function executeCallbacks($type, $args = [], $always_execute = false) {
        foreach($this->services as $key => $service) {
            if($always_execute || $service->isEnabled()) {
                if (method_exists($service, $type)) {
                    call_user_func_array([$service, $type], $args);
                }
            }
        }
    }

    public function formatLine($string, $style, $verbosity) {

        if(is_array($string)) {
            if(count($string) > 1) {
                $string = vsprintf($string[0], array_slice($string, 1));
            }
        }

        $string = str_replace('%message%', $string, $this->lineTemplate);
        $string = $this->formatLevel($string, $style);

        foreach($this->services as $service) {
            if(method_exists($service, 'formatLine') && $service->isEnabled()) {
                $string = $service->formatLine($string, $style, $verbosity);
            }
        }

        return $string;
    }

    public function getLevel($level) {
        if(null === $level) {
            $level = 'debug';
        }

        return strtoupper($level);
    }

    public function formatLevel($line, $level) {
        $level = sprintf('[%s]', $this->getLevel($level));
        return str_replace('%level%', $level, $line);
    }

    public function infof($format, ...$args) {
        $string = $this->sprintLine($format, $args);
        $this->info($string);
    }

    public function warnf($format, ...$args) {
        $string = $this->sprintLine($format,  $args);
        $this->warn($string);
    }

    public function errorf($format, ...$args) {
        $string = $this->sprintLine($format, $args);
        $this->error($string);
    }

    public function commentf($format, ...$args) {
        $string = $this->sprintLine($format, $args);
        $this->comment($string);
    }

    public function linef($format, ...$args) {
        $string = $this->sprintLine($format, $args);
        $this->line($string);
    }

    public function sprintLine($format, $args) {
        return vsprintf($format, $args);
    }

    public function line($string, $style = NULL, $verbosity = NULL)
    {
        $string = $this->formatLine($string, $style, $verbosity);

        parent::line($string, $style, $verbosity);
    }

    public function title($title) {
        $this->line($title, 'fg=black;bg=white');
    }

    public function header($message) {
        $this->output->writeln(sprintf('<fg=cyan;bg=default>%s</>', $message));
    }

    public function listing($elements) {
        $this->output->listing($elements);
    }

    /**
     * @param string $class
     * @throws InvalidServiceException
     */
    protected function registerService(string $class) {
        $service = new $class($this);
        $name = camel_case($service->getServiceName());
        if(!isset($this->services[$name])) {
            $this->services[$name] = $service;
        } else {
            throw new InvalidServiceException("Service " . $class . " is already registered.");
        }
    }

    public function getServices() {
        return $this->services;
    }

    protected function onComplete() {
        $this->executeCallbacks('onComplete');
    }

    protected function beforeRun() {
        $this->executeCallbacks('beforeRun');
    }

    protected function afterRun() {
        $this->executeCallbacks('afterRun');
    }

    protected function beforeExecute() {
        $this->executeCallbacks('beforeExecute');
    }

    protected function afterExecute() {
        $this->executeCallbacks('afterExecute');
    }

    protected function onException(\Exception $e) {
        $this->executeCallbacks('onException', [$e]);
    }

    public function enable() {
        $this->enabled = true;
    }

    public function disable() {
        $this->enabled = false;
    }

    public function isEnabled() {
        return $this->enabled;
    }

    public function failed() {
        return $this->failed;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $result = null;

        $result = parent::run($input, $output);

        $this->afterRun();
        $this->onComplete();

        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->beforeExecute();

        if($this->isEnabled()) {

            $executed = parent::execute($input, $output);

            $this->afterExecute();

            return $executed;
        }
    }

    /**
     * @param string $serviceName
     * @return Service | null
     */
    private function getService($serviceName) {
        $service = array_get($this->services, $serviceName, false);

        if($service) {
            return $service;
        }

        return null;
    }

    /**
     * @param $name
     * @return mixed
     * @throws \ErrorException
     */
    public function __get($name)
    {
        if(!isset($this->{$name})) {

            $service = $this->getService($name);

            if(!$service) {
                throw new \ErrorException("Invalid property " . get_called_class() . '::$' . $name);
            }

            return $service;

        } else {
            return $this->{$name};
        }
    }
}