<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccessToClickbankjvzooOrders extends Migration
{
    public function up()
    {
        // When the buyer first used what they bought (a download, a code redeemed), and how often since.
        Schema::table('clickbankjvzoo_orders', function (Blueprint $table) {
            $table->timestamp('accessed_at')->nullable()->after('bought_at');
            $table->unsignedInteger('accesses')->default(0)->after('accessed_at');
        });
    }

    public function down()
    {
        Schema::table('clickbankjvzoo_orders', function (Blueprint $table) {
            $table->dropColumn(['accessed_at', 'accesses']);
        });
    }
}
