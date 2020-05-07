<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Andrewlamers\LaravelAdvancedConsole\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateCommandHistoryMetadataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())
            ->create('command_history_metadata', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('command_history_id');

            $table->string('caller_path')->nullable();
            $table->text('caller_environment')->nullable();
            $table->string('caller_hostname')->nullable();
            $table->string('caller_user')->nullable();
            $table->integer('caller_uid')->nullable();
            $table->integer('caller_gid')->nullable();
            $table->bigInteger('caller_inode')->nullable();

            $table->integer('num_insert_statements')->nullable();
            $table->integer('num_select_statements')->nullable();
            $table->integer('num_update_statements')->nullable();
            $table->integer('num_delete_statements')->nullable();

            $table->string('git_branch')->nullable();
            $table->string('git_commit')->nullable();
            $table->timestamp('git_commit_date')->nullable();

            $table->text('extra_metadata')->nullable();
            $table->text('exception_trace')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection($this->getConnection())->dropIfExists('command_history_metadata');
    }
}
