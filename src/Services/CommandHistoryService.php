<?php
namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Exceptions\CommandHistoryOutputException;
use Andrewlamers\LaravelAdvancedConsole\Facades\CommandConfig;
use Andrewlamers\LaravelAdvancedConsole\Models\CommandHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CommandHistoryService extends Service
{
    /**
     * @var CommandHistory $model
     */
    protected $model;

    /**
     * @var string $dateFormat - Format for timestamps
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $outputBuffer = [];

    protected $logOutput = true;

    protected $compressOutput = true;

    protected $history;

    protected $messageCounts = [
        'error' => 0,
        'warning' => 0,
        'info' => 0,
        'line' => 0
    ];

    /**
     * @var int $outputSaveInterval - How many lines to wait between syncing output to database
     */
    protected $outputSaveInterval = 5;

    public function compressOutput($compress = null) {
        if($compress !== null) {
            $this->compressOutput = $compress;
        }

        return $this->compressOutput;
    }

    /**
     * @throws CommandHistoryOutputException
     */
    public function setOutputCompressed(): void {
        if($this->logOutput() && $this->compressOutput()) {
            $conn = $this->model->output->getConnection();
            $pdo = $conn->getPdo();
            $table = $this->model->output->getTable();
            $stmt = $pdo->prepare('UPDATE ' . $table . ' SET `output` = COMPRESS(output) WHERE id = ?');
            if(!$stmt->execute([$this->model->output->id])) {
                throw new CommandHistoryOutputException('Error setting output compression.');
            }
        }
    }

    public function initialize($input, $output): void
    {
        $this->history = CommandHistory::on(CommandConfig::getConnection());

        $this->model = new CommandHistory($this->getModelAttributes());

        parent::initialize($input, $output);

        $this->model->running();
        $this->model->save();
        $this->model->metadata()->create($this->getMetadataAttributes());
        $this->checkProcessState();
    }

    public function getLastRun() {

        $last_run = CommandHistory::where('command_history', $this->command->getName())
            ->where('completed', true)
            ->orderBy('start_time', 'desc')
            ->first();

        return $last_run;
    }

    public function checkProcessState(): void {
        $processes = $this->getRunningProcesses();

        foreach($processes as $process) {
            $running = false;
            if($process->process_id) {
                $running = $this->getProcessStatusById($process->process_id);
                if($running === '') {
                    $process->fail();
                }
            } else {
                $process->fail();
            }
        }
    }

    public function getRunningProcesses() {
        return CommandHistory::where('command_name', $this->command->getName())
            ->where('running', true)
            ->where('process_id', '!=', $this->getPID())
            ->get();
    }

    protected function getProcessStatusById($pid): string {
        return $this->runProcess('ps -p ' . $pid . ' | grep php');
    }

    public function getModel() {
        return $this->model;
    }

    public function onComplete(): void
    {
        $this->model->updateLineCounts($this->messageCounts);
        $this->model->end($this->getEndTime());
        $this->model->durationMs($this->getDurationMs());
        $this->model->peakMemoryUsage($this->getPeakMemoryUsage());
        $this->model->save();
        $this->saveOutput();
        $this->model->metadata->save();
        $this->model->refresh();
    }

    protected function getPeakMemoryUsage() {
        return memory_get_peak_usage();
    }

    public function onWrite($output): void {
        $this->appendOutput($output);

        if(count($this->outputBuffer) % $this->outputSaveInterval === 0 && count($this->outputBuffer) > 40) {
            $this->model->output->save();
        }
    }

    public function afterExecute(): void
    {
        $this->model->complete();
    }

    public function appendOutput($output): void {
        if($this->logOutput()) {
            if (!$this->model->output) {
                $this->createOutput();
            }

            $this->outputBuffer[] = $output;
            $this->model->output->output = implode("\n", $this->outputBuffer);
        }
    }

    protected function createOutput(): void {
        if($this->logOutput()) {
            $this->model->output()->create();
            $this->model->refresh();
        }
    }

    public function saveOutput(): void {
        if($this->logOutput()) {
            $this->model->output->save();
        }
    }

    public function onException(Exception $e): void {
        $this->model->exception($e);
    }

    protected function getModelAttributes(): array {

        $attributes = [
            'command_name' => $this->command->getName(),
            'start_time' => $this->getStartTime(),
            'process_id' => $this->getPID(),
            'running' => true
        ];

        return $attributes;
    }

    protected function getMetadataAttributes(): array {
        $meta = $this->command->metadata;
        $data = [
            'caller_path' => $meta->getPath(),
            'caller_environment' => $meta->getEnvironment(),
            'caller_hostname' => $meta->getHost(),
            'caller_user' => $meta->getUser(),
            'caller_uid' => $meta->getUID(),
            'caller_gid' => $meta->getGID(),
            'caller_inode' => $meta->getInode(),
            'git_branch' => $meta->getGitBranch(),
            'git_commit' => $meta->getGitCommitHash(),
            'git_commit_date' => $meta->getGitCommitDate()
        ];

        return $data;
    }

    public function getStartTime(): string {
        return $this->getDate($this->command->benchmark->getStartTime());
    }

    public function getEndTime(): string {
        return $this->getDate($this->command->benchmark->getEndTime());
    }

    public function getDurationMs() {
        return ($this->command->benchmark->getTotalElapsedTime() * 1000);
    }

    /**
     * @param $line
     * @param $style
     * @return mixed
     */
    public function formatLine($line, $style) {
        if($style === null) {
            $style = 'line';
        }

        $this->incrementLineCount($style);

        return $line;
    }

    protected function incrementLineCount($type): void {
        if(isset($this->messageCounts[$type])) {
            $this->messageCounts[$type]++;
        }
    }

    protected function getDate($timestamp): string {
        $date = Carbon::createFromTimestampMs($timestamp * 1000, 'UTC');
        return $date->format($this->dateFormat);
    }

    protected function logOutput(): bool {
        if(property_exists($this->command, 'logOutput')) {
            return $this->command->logOutput;
        }

        return $this->logOutput;
    }
}