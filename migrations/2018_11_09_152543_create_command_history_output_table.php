<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Andrewlamers\LaravelAdvancedConsole\Database\Migrations\Migration;

class CreateCommandHistoryOutputTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())
            ->create('command_history_output', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('command_history_id');
            $table->binary('output')->nullable();
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
        Schema::connection($this->getConnection())->dropIfExists('command_history_output');
    }
}
