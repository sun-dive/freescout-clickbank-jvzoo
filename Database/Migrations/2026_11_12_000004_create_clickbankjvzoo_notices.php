<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClickbankjvzooNotices extends Migration
{
    public function up()
    {
        // What the member site is to be told: a used code's order changed status. Kept until the member site has it.
        Schema::create('clickbankjvzoo_notices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->index();
            $table->string('code', 16);
            $table->string('member', 64);
            $table->string('status', 16);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clickbankjvzoo_notices');
    }
}
