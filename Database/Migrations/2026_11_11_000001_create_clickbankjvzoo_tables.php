<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClickbankjvzooTables extends Migration
{
    public function up()
    {
        // An order: one product bought, from either seller. Its status follows its latest event.
        Schema::create('clickbankjvzoo_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('source', 16);
            $table->string('receipt', 64);
            $table->string('item', 128);
            $table->string('parent_receipt', 64)->nullable();
            $table->string('title', 255)->default('');
            // 191 characters: the longest a utf8mb4 column can be and still be indexed
            $table->string('email', 191)->index();
            $table->string('name', 255)->default('');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('');
            $table->string('affiliate', 64)->nullable();
            $table->text('variables')->nullable();
            $table->string('status', 16);
            $table->boolean('test')->default(false);
            $table->timestamp('bought_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'receipt', 'item']);
        });

        // Every event an order has had. A notice sent twice (the sellers retry) is kept once.
        Schema::create('clickbankjvzoo_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->index();
            $table->string('type', 32);
            $table->timestamp('happened_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['order_id', 'type', 'happened_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('clickbankjvzoo_events');
        Schema::dropIfExists('clickbankjvzoo_orders');
    }
}
