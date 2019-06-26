<?php
namespace Andrewlamers\LaravelAdvancedConsole\Services;

class MetadataService extends Service
{
    public $metadata = [];

    public function initialize($input, $output): void
    {
        $this->createMetadata();
    }

    public function beforeExecute(): void {
        $this->outputMetadata();
    }

    protected function outputMetadata() {
        $metadata = $this->formatMetadata();

        foreach ($metadata as $data) {

            if (isset($data['title'])) {
                $this->command->header($data['title']);
            }

            $this->command->listing($data['data']);
        }

        $this->command->line(sprintf('Starting command %s...', $this->command->getName()));
    }

    public function add($name, $value = '', $indent = 0, $prefix = '') {

        $data = ['name' => $name, 'value' => $value, 'indent' => $indent, 'prefix' => $prefix, 'title' => false];

        $args = func_get_args();

        if(count($args) === 1) {
            unset($data['value']);
            $data['title'] = true;
        }

        $this->metadata[] = $data;

        return $this;
    }

    protected function getDatabaseConfig() {
        $connections = $this->config('database.connections');

        foreach($connections as $name => $connection) {
            $connections[$name] = array_dot(array_except($connection, 'password'));
        }

        return $connections;
    }

    protected function formatDatabaseConfig(array $configs): void {
        foreach($configs as $name => $config) {
            $this->add('', $name);

            foreach($config as $key => $value) {
                if (is_bool($value)) {
                    $value = (boolval($value) ? 'True' : 'False');
                }
                $this->add($key, $value, 2);
            }
        }
    }

    protected function formatMetadata(): array {
        $listingGroups = [];

        $groupNumber = 0;
        foreach($this->metadata as $data) {

            if(array_key_exists($groupNumber, $listingGroups) && !is_array($listingGroups[$groupNumber])) {
                $listingGroups[$groupNumber] = [
                    'title' => '',
                    'data' => []
                ];
            }

            if($data['title']) {
                $groupNumber++;
                $listingGroups[$groupNumber]['title'] = $data['name'];
            } else {

                if (isset($data['value']) && ($data['value'] === '' || is_array($data['value']))) {
                    continue;
                }

                if (isset($data['value'])) {
                    if ($data['prefix']) {
                        $data['name'] = sprintf('%s %s', $data['prefix'], $data['name']);
                    }

                    if ($data['indent']) {
                        $data['name'] = sprintf('<info>%s%s</info>', str_pad('', $data['indent'], ' ', STR_PAD_LEFT), $data['name']);
                    }

                    if ($data['name'] !== '') {
                        $data['name'] = sprintf('<info>%-36s</info>: ', $data['name']);
                    } else {
                        $data['value'] = sprintf('<comment>%s</comment>', $data['value']);
                    }

                    if($data['value'] === 'Enabled') {
                        $data['value'] = sprintf('<info>%s</info>', $data['value']);
                    }

                    if($data['value'] === 'Disabled') {
                        $data['value'] = sprintf('<fg=red;bg=default>%s</>', $data['value']);
                    }

                    $line = sprintf('%s%s', $data['name'], $data['value']);

                    $listingGroups[$groupNumber]['data'][] = $line;
                }
            }
        }

        return $listingGroups;
    }

    public function getGitInfo() {
        return sprintf('%s: %s (%s)', $this->getGitBranch(), $this->getGitCommitHash(),
            $this->getGitCommitDate());
    }

    public function getTimezone() {
        return date_default_timezone_get();
    }

    protected function createMetadata(): void {

        $this->add('Location', $this->getPath())
            ->add('Host', $this->getHost())
            ->add('PHP Version', $this->getPhpVersion())
            ->add('Memory Limit', ini_get('memory_limit'))
            ->add('Time Limit', ini_get('max_execution_time'))
            ->add('Environment', $this->getEnvironment())
            ->add('App Debug', $this->getAppDebug())
            ->add('User', $this->getUser())
            ->add('UID', $this->getUID())
            ->add('GID', $this->getGID())
            ->add('PID', $this->getPID())
            ->add('Inode', $this->getInode())
            ->add('Timezone', $this->getTimezone());

        if($this->isGitRepo()) {
            $this->add('Git Branch', sprintf('%s', $this->getGitBranch()))
                ->add('Git Committer', sprintf('%s (%s)', $this->getGitCommitterName(), $this->getGitCommitterEmail()))
                ->add('Last Commit Date',  $this->getGitCommitDate())
                ->add('Last Commit', sprintf('%s', $this->getGitCommitMessage()));
        } else {
            $this->add('Git: ', 'Not a git repository');
        }

        if(method_exists($this, 'getLogFullPath')) {
            $this->add('Logging Output To: ', $this->getLogFullPath());
        }

        $this->add('Database');
        $database = $this->getDatabaseConfig();
        $this->formatDatabaseConfig($database);

        $options = array_filter($this->command->options());

        if(count($options) > 0) {
            $this->add('Structure');
            foreach ($options as $name => $value) {
                if (is_bool($value)) {
                    $value = (boolval($value) ? 'True' : 'False');
                }
                $this->add($name, $value, 2, '-');
            }
        }

        $arguments = $this->command->arguments();

        if(count($arguments) > 0) {
            $this->add('Arguments');

            foreach ($arguments as $name => $value) {
                if (is_bool($value)) {
                    $value = (boolval($value) ? 'True' : 'False');
                }
                $this->add($name, $value, 2, '-');
            }
        }

        $services = $this->command->getServices();

        if(count($services) > 0) {

            $this->add('Services');

            foreach($services as $service) {
                $this->add($service->getServiceClass(), ($service->isEnabled() ? 'Enabled' : 'Disabled'), 1);
            }
        }
    }
}