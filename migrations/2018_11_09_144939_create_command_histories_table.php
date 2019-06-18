<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Andrewlamers\LaravelAdvancedConsole\Facades\CommandConfig;
use Andrewlamers\LaravelAdvancedConsole\Database\Migrations\Migration;

class CreateCommandHistoriesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())->create('command_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('command_name');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->decimal('duration_ms', 10, 3)->nullable();
            $table->integer('peak_memory_usage_bytes')->nullable();
            $table->integer('process_id')->nullable();

            $table->boolean('running')->default(false);
            $table->boolean('completed')->default(false);
            $table->boolean('failed')->default(false);
            $table->boolean('exception')->default(false);

            $table->integer('warning_message_count')->nullable();
            $table->integer('info_message_count')->nullable();
            $table->integer('error_message_count')->nullable();
            $table->integer('line_message_count')->nullable();

            $table->boolean('out_of_memory_exception')->default(false);


            $table->timestamp('lock_acquired_time')->nullable();

            $table->index(['command_name' , 'running']);
            $table->index(['created_at'], 'command_histories_created_at_index');

            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($this->getConnection())->statement("ALTER TABLE `command_histories` MODIFY start_time TIMESTAMP(6) NULL");
        DB::connection($this->getConnection())->statement("ALTER TABLE `command_histories` MODIFY end_time TIMESTAMP(6) NULL");
        DB::connection($this->getConnection())->statement("ALTER TABLE `command_histories` MODIFY lock_acquired_time TIMESTAMP(6) NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection($this->getConnection())->dropIfExists('command_histories');
    }
}
