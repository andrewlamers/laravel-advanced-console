<?php
namespace Andrewlamers\LaravelAdvancedConsole\Services;

use Andrewlamers\LaravelAdvancedConsole\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfileService extends Service
{
    /**
     * @var Collection $queryLog;
     */
    protected $queryLog;

    protected static $queryTypes = [
        'select' => ['select .* from .*'],
        'insert' => ['insert into (.*)'],
        'update' => ['update (.*) set .*'],
        'delete' => ['delete from (.*)', 'update (.*) set .* deleted_at .*']
    ];

    protected $statementCounts = [];
    protected $totalQueryTime = [];

    public function beforeExecute(): void
    {
        DB::listen(function($query) {
            $this->appendQueryLog($query);
        });

        $this->queryLog = new Collection();
    }

    protected function appendQueryLog($query): void {

        if($this->queryIsProfileable($query->sql)) {

            $type = $this->parseQueryType($query->sql);

            $this->queryLog->push([
                'sql' => str_replace_array('?', $query->bindings, $query->sql),
                'time' => $query->time,
                'type' => $type,
                'bindings' => $query->bindings
            ]);
        }
    }

    protected function incrementStatementCount($type, $count): void {
        if(!isset($this->statementCounts[$type])) {
            $this->statementCounts[$type] = 0;
        }

        $this->statementCounts[$type] += $count;
    }

    protected function parseQueryType($sql) {
        preg_match('/^(?<type>[a-zA-Z]+)/', $sql, $matches);
        return array_get($matches, 'type', null);
    }

    protected function queryIsProfileable($sql): bool {
        if(preg_match('/(command_histories|command_history_output|command_history_metadata)/', $sql)) {
            return false;
        }

        return true;
    }

    protected function outputProfileTable(): void {
        $this->command->header('Database Profile');
        $row = [];

        $types = $this->queryLog->groupBy('type');

        foreach($types as $type => $statements) {
            $row[$type] = $statements->count() . ' (' . $statements->sum('time') . 'ms)';
        }

        if(count($row) > 0) {
            $this->command->table(array_keys($row), [$row]);
        } else {
            $this->command->info('No queries executed.');
        }
    }

    protected function outputQueries(): void {

        $queries = $this->queryLog->groupBy('sql');

        $arr = [];

        foreach($queries as $sql => $query) {

            $arr[] = [
                'sql' => $sql,
                'time' => $query->sum('time'),
                'count' =>  $query->count()
            ];
        }

        $header = array_keys($arr[0]);
        $this->command->header('Queries');
        $this->command->table($header, $arr);
    }

    public function onComplete(): void
    {
        $this->outputProfileTable();
        $this->outputQueries();
    }
}