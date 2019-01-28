<?php
/**
 * Created by PhpStorm.
 * User: andrewlamers
 * Date: 11/9/18
 * Time: 6:47 AM
 */

namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Exceptions\CommandHistoryOutputException;
use Andrewlamers\LaravelAdvancedConsole\Models\CommandHistory;
use Carbon\Carbon;

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

    private $memoryBuffer;

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

    public function setOutputCompressed() {
        $conn = $this->model->output->getConnection();
        $pdo = ($conn->getPdo());
        $table = $this->model->output->getTable();
        $stmt = $pdo->prepare('UPDATE ' . $table . ' SET `output` = COMPRESS(output) WHERE id = ?');
        return $stmt->execute([$this->model->output->id]);
    }

    public function initialize($input, $output)
    {
        parent::initialize($input, $output);
        $this->model = new CommandHistory($this->getModelAttributes());
        $this->model->running();
        $this->model->save();
        $this->model->metadata()->create($this->getMetadataAttributes());
        $this->checkProcessState();
    }

    public function getLastRun() {

        $last_run = CommandHistory::where("command_history", $this->command->getName())
            ->where("completed", true)
            ->orderBy("start_time", "desc")
            ->first();

        return $last_run;
    }

    public function checkProcessState() {
        $processes = $this->getRunningProcesses();

        foreach($processes as $process) {
            $running = false;
            if($process->process_id) {
                $running = $this->getProcessStatusById($process->process_id);
                if(strlen($running) < 1) {
                    $process->fail();
                }
            } else {
                $process->fail();
            }
        }
    }

    public function getRunningProcesses() {
        return CommandHistory::where("command_name", $this->command->getName())
            ->where("running", true)
            ->where("process_id", "!=", $this->getPID())
            ->get();
    }

    protected function getProcessStatusById($pid) {
        $status = $this->runProcess('ps -p ' . $pid . ' | grep php');
        return $status;
    }

    public function getModel() {
        return $this->model;
    }

    public function onComplete()
    {
        $this->model->end($this->getEndTime());
        $this->model->durationMs($this->getDurationMs());
        $this->model->save();
        $this->saveOutput();
        $this->model->metadata->save();
        $this->model->refresh();
    }

    public function onWrite($output) {
        $this->appendOutput($output);

        if(count($this->outputBuffer) % $this->outputSaveInterval === 0 && count($this->outputBuffer) > 40) {
            $this->model->output->save();
        }
    }

    public function afterExecute()
    {
        $this->model->complete();
    }

    public function appendOutput($output) {
        if($this->logOutput()) {
            if (!$this->model->output) {
                $this->createOutput();
            }

            $this->outputBuffer[] = $output;
            $this->model->output->output = join("\n", $this->outputBuffer);
        }
    }

    protected function createOutput() {
        if($this->logOutput()) {
            $this->model->output()->create();
            $this->model->refresh();
        }
    }

    public function saveOutput() {
        if($this->logOutput()) {
            $this->model->output->save();
        }
    }

    public function onException(\Exception $e) {
        $this->model->exception($e);
    }

    protected function getModelAttributes() {

        $attributes = [
            'command_name' => $this->command->getName(),
            'start_time' => $this->getStartTime(),
            'process_id' => $this->getPID(),
            'running' => true
        ];

        return $attributes;
    }

    protected function getMetadataAttributes() {
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

    public function getStartTime() {
        return $this->getDate($this->command->benchmark->getStartTime());
    }

    public function getEndTime() {
        return $this->getDate($this->command->benchmark->getEndTime());
    }

    public function getDurationMs() {
        return ($this->command->benchmark->getTotalElapsedTime() * 1000);
    }

    protected function getDate($timestamp) {
        $date = Carbon::createFromTimestampMs($timestamp * 1000, 'UTC');
        return $date->format($this->dateFormat);
    }

    protected function logOutput() {
        if(property_exists($this->command, 'logOutput')) {
            return $this->command->logOutput;
        }

        return $this->logOutput;
    }
}