<?php
namespace Andrewlamers\LaravelAdvancedConsole;

use Andrewlamers\LaravelAdvancedConsole\Console\Formatter\OutputFormatter;
use Andrewlamers\LaravelAdvancedConsole\Exceptions\CommandHistoryOutputException;
use Andrewlamers\LaravelAdvancedConsole\Services\BenchmarkService;
use Andrewlamers\LaravelAdvancedConsole\Exceptions\InvalidServiceException;
use Andrewlamers\LaravelAdvancedConsole\Services\CommandHistoryService;
use Andrewlamers\LaravelAdvancedConsole\Services\LockingService;
use Andrewlamers\LaravelAdvancedConsole\Services\MetadataService;
use Andrewlamers\LaravelAdvancedConsole\Services\ProfileService;
use Andrewlamers\LaravelAdvancedConsole\Services\Service;
use Illuminate\Console\Command as BaseCommand;
use Illuminate\Container\Container;
use Exception;
use ErrorException;
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

    /**
     * @var OutputFormatter $outputFormatter
     */
    protected $outputFormatter;

    /**
     * @var bool $enabled - Enable command, setting to false will prevent execution.
     */
    protected $enabled = true;

    /**
     * @var bool $failed - Command failed state
     */
    protected $failed = false;

    /**
     * @var string $memoryBuffer - Buffer bytes to allocate ram to be freed on exception so an error message can be displayed for OOM exceptions.
     */
    private $memoryBuffer;

    protected $config;

    /**
     * Command constructor.
     *
     * @throws InvalidServiceException
     */
    public function __construct() {

        $this->setLaravel(Container::getInstance());
        $this->config = $this->getConfig();
        $this->registerService(new CommandHistoryService);
        $this->registerService(new BenchmarkService);
        $this->registerService(new MetadataService);
        $this->registerService(new LockingService);
        $this->registerService(new ProfileService);
        $this->outputFormatter = new OutputFormatter(true);
        $this->outputFormatter->setCommand($this);

        parent::__construct();
    }

    /**
     * @return InputInterface
     */
    public function getInput(): InputInterface {
        return $this->input;
    }

    protected function getConfig() {
        return config('laravel-advanced-console');
    }

    protected function configure(): void
    {
        $this->executeCallbacks('configure', [], true);
    }

    /**
     * setMemoryBuffer
     * allocates 5MB of memory to have overhead on shutdown for handling exceptions and out
     * of memory errors.
     * @return void
     */
    private function setMemoryBuffer(): void {
        $this->memoryBuffer = str_repeat('*', 1024 * 1024 * 5);
    }

    /**
     * clearMemoryBuffer
     * Clears the memory buffer on shutdown to leave enough memory for handling
     * fatal errors and cleanup on out of memory errors
     */
    private function clearMemoryBuffer(): void {
        $this->memoryBuffer = null;
    }

    /**
     * Shutdown handler to clean up command history after command ends or throws exceptions
     * @throws CommandHistoryOutputException
     */
    public function onShutdown(): void {
        $this->clearMemoryBuffer();
        $error = error_get_last();

        if($this->commandHistory->isEnabled()) {
            if ($error) {
                $this->commandHistory->getModel()->fail();
            }

            $this->commandHistory->saveOutput();
            $this->commandHistory->setOutputCompressed();
        }
    }

    private function registerShutdownHandler(): void {
        $this->setMemoryBuffer();
        register_shutdown_function([$this, 'onShutdown']);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->registerShutdownHandler();

        parent::initialize($input, $output);

        $output->setFormatter($this->outputFormatter);
        $this->setMemoryLimit($this->memoryLimit);
        $this->setTimeLimit($this->timeLimit);
        $this->executeCallbacks('initialize', [$input, $output]);
        $this->beforeRun();
    }

    private function setTimeLimit($seconds): void {
        set_time_limit($seconds);
    }

    private function setMemoryLimit($limit): void {
        ini_set('memory_limit', $limit);
    }

    public function executeCallbacks($type, $args = [], $always_execute = false): void {
        foreach($this->services as $key => $service) {
            if($always_execute || $service->isEnabled()) {
                if (method_exists($service, $type)) {
                    call_user_func_array([$service, $type], $args);
                }
            }
        }
    }

    public function formatLine($string, $style, $verbosity) {

        if(is_array($string) && count($string) > 1) {
            $string = vsprintf($string[0], array_slice($string, 1));
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

    public function formatLevel($line, $level): string {
        $level = sprintf('[%s]', $this->getLevel($level));
        return str_replace('%level%', $level, $line);
    }

    public function infof($format, ...$args): void {
        $string = $this->sprintLine($format, $args);
        $this->info($string);
    }

    public function warnf($format, ...$args): void {
        $string = $this->sprintLine($format,  $args);
        $this->warn($string);
    }

    public function errorf($format, ...$args): void {
        $string = $this->sprintLine($format, $args);
        $this->error($string);
    }

    /**
     * @inheritDoc @linef
     * @param       $format
     * @param mixed ...$args
     */
    public function commentf($format, ...$args): void {
        $string = $this->sprintLine($format, $args);
        $this->comment($string);
    }

    /**
     * Accepts arguments to print a formatted line to console through sprintf
     * @param       $format
     * @param mixed ...$args
     */
    public function linef($format, ...$args): void {
        $string = $this->sprintLine($format, $args);
        $this->line($string);
    }

    protected function sprintLine($format, $args): string {
        $args = $this->cleanSprintArgs($args);
        return vsprintf($format, $args);
    }

    /**
     * cleans arguments for sprintf to convert objects into json for output
     * @param $args - array of arguments to print
     * @return array
     */
    protected function cleanSprintArgs(array $args): array {
        foreach($args as $index => $arg) {
            if(is_array($arg) || is_object($arg)) {
                $args[$index] = json_encode($arg);
            }
        }

        return $args;
    }

    /**
     * @param string|array $string
     * @param null   $style
     * @param null   $verbosity
     * @return void
     */
    public function line($string, $style = NULL, $verbosity = NULL) : void
    {
        $string = $this->formatLine($string, $style, $verbosity);

        parent::line($string, $style, $verbosity);
    }

    /**
     * @param string|array $title
     */
    public function title($title) : void  {
        $this->line($title, 'fg=black;bg=white');
    }

    /**
     * @param string|array $message
     */
    public function header($message): void {
        $this->output->writeln(sprintf('<fg=cyan;bg=default>%s</>', $message));
    }

    /**
     * @param array $elements
     */
    public function listing(array $elements): void {
        $this->output->listing($elements);
    }

    public function hasWarnings(): bool {
        return $this->getWarningCount() > 0;
    }

    public function hasErrors(): bool {
        return $this->getErrorCount() > 0;
    }

    public function getWarningCount() {
        return $this->commandHistory->getMessageCounts('warning');
    }

    public function getErrorCount() {
        return $this->commandHistory->getMessageCounts('error');
    }

    /**
     * @param Service $service
     * @throws InvalidServiceException
     */
    protected function registerService(Service $service): void {

        $service->setCommand($this);
        
        $name = camel_case($service->getServiceName());

        if(!isset($this->services[$name])) {
            $this->services[$name] = $service;
        } else {
            throw new InvalidServiceException('Service' . $service->getServiceClass() . ' is already registered.');
        }
    }

    /**
     * @return array|Service
     */
    public function getServices(): array {
        return $this->services;
    }

    /**
     * @return void
     */
    protected function onComplete(): void {
        $this->executeCallbacks('onComplete');
    }

    protected function beforeRun(): void {
        $this->executeCallbacks('beforeRun');
    }

    protected function afterRun(): void {
        $this->executeCallbacks('afterRun');
    }

    protected function beforeExecute(): void {
        $this->executeCallbacks('beforeExecute');
    }

    protected function afterExecute(): void {
        $this->executeCallbacks('afterExecute');
    }

    /**
     * @param Exception $e
     */
    protected function onException(Exception $e): void {
        $this->executeCallbacks('onException', [$e]);
    }

    public function enable(): void {
        $this->enabled = true;
    }

    public function disable(): void {
        $this->enabled = false;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function failed(): bool {
        return $this->failed;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
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

        return null;
    }

    /**
     * @param string $serviceName
     * @return Service | null
     */
    private function getService($serviceName): ?Service {
        $service = array_get($this->services, $serviceName, false);

        if($service) {
            return $service;
        }

        return null;
    }

    /**
     * @param $name
     * @return mixed
     * @throws ErrorException
     */
    public function __get($name)
    {
        if(!isset($this->{$name})) {

            $service = $this->getService($name);

            if(!$service) {
                throw new ErrorException('Invalid property ' . static::class . '::$' . $name);
            }

            return $service;

        }

        return $this->{$name};
    }

    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset($this->{$name});
    }
}