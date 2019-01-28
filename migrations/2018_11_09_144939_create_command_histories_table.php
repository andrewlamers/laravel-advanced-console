<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateCommandHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('command_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('command_name');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->float('duration_ms')->nullable();
            $table->integer('process_id')->nullable();

            $table->boolean('running')->default(false);
            $table->boolean('completed')->default(false);
            $table->boolean('failed')->default(false);
            $table->boolean('exception')->default(false);
            $table->boolean('out_of_memory_exception')->default(false);


            $table->timestamp('lock_acquired_time')->nullable();

            $table->index(['command_name' , 'running']);
            $table->index(['created_at'], 'command_histories_created_at_index');

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE `command_histories` MODIFY start_time TIMESTAMP(6) NULL");
        DB::statement("ALTER TABLE `command_histories` MODIFY end_time TIMESTAMP(6) NULL");
        DB::statement("ALTER TABLE `command_histories` MODIFY lock_acquired_time TIMESTAMP(6) NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('command_histories');
    }
}
