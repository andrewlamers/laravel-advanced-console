<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 3/2/18
 * Time: 2:34 PM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Command;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class Service
{
    /**
     * @var Command $command
     */
    protected $command;
    protected $application;
    protected $enabled = false;
    protected $canDisable = true;

    public function __construct(Command $command) {
        $this->setCommand($command);
        $this->setApplication($command->getLaravel());
    }

    public function configure() {
        if($this->isEnabled()) {
            $this->command->addOption($this->getDisableOptionName(),
                NULL, NULL,
                'Disable ' . $this->getServiceName() . ' service.');
        } else {
            $this->command->addOption($this->getEnableOptionName(),
                null, null,
                'Enable ' . $this->getServiceName() . ' service.');
        }
    }

    public function initialize($input, $output) {
        if($this->isDisabled() && $this->canDisable()) {
            $this->disable();
        }
    }

    protected function getDisableOptionName() {
        return strtolower('disable-' . snake_case($this->getServiceName(), '-'));
    }

    protected function getEnableOptionName() {
        return strtolower('enable-' . snake_case($this->getServiceName(), '-'));
    }

    protected function canDisable() {
        return $this->canDisable;
    }

    public function isDisabled() {

        if($this->option($this->getDisableOptionName())) {
            return true;
        }

        return false;
    }

    protected function option($name) {
        return $this->command->option($name);
    }

    protected function setCommand($command) {
        $this->command = $command;
    }

    protected function setApplication(Application $application) {
        $this->application = $application;
    }

    protected function runProcess($command, $path = null) {
        $matches = [];

        preg_match_all('/\{(?P<function>[a-zA-Z]+)\}/i', $command, $matches);

        if(count($matches) > 0) {
            if(isset($matches['function']) && count($matches['function']) > 0) {
                foreach($matches['function'] as $function) {
                    if(method_exists($this, $function)) {
                        $result = call_user_func([$this, $function]);
                        if(is_string($result) || is_integer($result)) {
                            $command = str_replace('{' . $function . '}', $result, $command);
                        }
                    }
                }
            }
        }

        if($path === null) {
            $path = $this->getPath();
        }

        $output = null;
        $process = new Process($command, $path);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        } else if($process->isTerminated()) {
            return trim($process->getErrorOutput());
        }

        throw new ProcessFailedException($process);
    }

    public function config($key) {
        return $this->application['config']->get($key);
    }

    public function getServiceClass() {
        return class_basename($this);
    }

    public function getServiceName() {
        $class = $this->getServiceClass();
        $base = str_replace("Service", '', $class);
        $name = ucfirst($base);
        return $name;
    }

    public function getEnableVariableName() {
        return sprintf('enable%s', $this->getServiceName());
    }

    public function setEnabled($enabled = true) {
        $name = $this->getEnableVariableName();

        if(isset($this->command->{$name})) {
            $this->command->{$name} = $enabled;
        }
    }

    public function enable() {
        $this->setEnabled(true);
    }

    public function disable() {
        $this->setEnabled(false);
    }

    public function isEnabled() {

        $name = $this->getEnableVariableName();

        if(isset($this->command->{$name})) {
            return $this->command->{$name};
        }

        return false;
    }

    protected function getPath() {
        $dir = $this->application->basePath();
        return $dir;
    }

    protected function getHost() {
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        return sprintf('%s (%s)', $hostname, $ip);
    }

    protected function getPhpVersion() {
        return phpversion();
    }

    protected function getEnvironment() {
        return $this->command->getLaravel()->environment();
    }

    protected function getUser() {
        return get_current_user();
    }

    protected function getPID() {
        return getmypid();
    }

    protected function getUID() {
        return getmyuid();
    }

    public function getGID() {
        return getmygid();
    }

    public function getInode() {
        return getmyinode();
    }

    public function isGitRepo() {
        $result = $this->runProcess('git -C {getPath} status');

        if(str_contains($result, 'fatal')) {
            return false;
        }

        return true;
    }

    public function getGitBranch() {
        return $this->runProcess('git rev-parse --abbrev-ref HEAD');
    }

    public function getGitCommitHash() {
        return $this->runProcess('git log --pretty="%H" -n1 HEAD');
    }

    public function getGitCommitDate() {
        try {
            return Carbon::parse($this->runProcess('git log --pretty="%ci" -n1 HEAD'))->tz('utc')->format('Y-m-d H:i:s');
        } catch(\Exception $e) {
            return null;
        }
    }

    public function getGitCommitterName() {
        return $this->runProcess('git log --pretty="%an" -n1 HEAD');
    }

    public function getGitCommitterEmail() {
        return $this->runProcess('git log --pretty="%ae" -n1 HEAD');
    }

    public function getGitCommitMessage() {
        return $this->runProcess('git log --pretty="%s" -n1 HEAD');
    }

    public function getAppDebug() {
        return ($this->command->getLaravel()->get('config')->get('app.debug') ? 'True' : 'False');
    }

    function onWrite($line) {}
    function onComplete() {}
    function onLoad() {}
    function beforeRun() {}
    function afterRun() {}
    function beforeExecute() {}
    function afterExecute() {}
    function onShutdown() {}
}