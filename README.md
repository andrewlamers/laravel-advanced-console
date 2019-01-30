## Laravel Advanced Console
Extension of Illuminate console command to add debugging and other utilities for more verbose
console programs.


#### Benchmarks

Outputs current time, memory usage, ms since command start and line type with each line
in the output.

    [2019-01-30 12:14:52][470ms][17.6MB][DEBUG] Starting command

#### Database Profile


#### Formatted Output Lines


#### Metadata
Outputs information about the running environment the command was executed in,
as well as database and other configuration that would be useful when debugging commands that 
run on a schedule

     * Location                            : /composer-packages/test
     * Host                                : 127.0.0.1
     * PHP Version                         : 7.2.9
     * Memory Limit                        : 256M
     * Time Limit                          : 300
     * Environment                         : local
     * App Debug                           : True


#### Process Lock Mutex

Commands can be locked to prevent executions overlapping.
Since the lock is acquired by the command history table, and by process id
it is a more reliable lock that doesnt get stuck locked if the command has a fatal error

#### Command Execution History
Enabled by default.
Disabled by setting

`
    $enableCommandHistory = false;
`

Stores a history of all commands run through the CLI into a database table with metadata 
and output. This is also where process locks will be obtained from.
This makes debugging and monitoring much easier when you have a large amount of unattended commands 
running on intervals. 

