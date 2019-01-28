## Laravel Advanced Console
Extension of Illuminate console command to add debugging and other utilities for more verbose
console programs.

Adds timestamps, memory usage, and execution time to end of program and on each line
sent to output.

Can profile SQL Statements used through laravels database log
Stores executed commands to a history table with output and execution times
Can set locks on commands to prevent running programmings from overlapping, since it goes off
the database and checks the process id to see if its actually still running it is more 
reliable than laravels mutex lock. 

