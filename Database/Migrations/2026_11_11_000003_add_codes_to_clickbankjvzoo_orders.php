<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ClickBankJVZoo\Misc\Codes;

class AddCodesToClickbankjvzooOrders extends Migration
{
    public function up()
    {
        // Each order's code, and the member it was redeemed for on the member site.
        Schema::table('clickbankjvzoo_orders', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->unique()->after('item');
            $table->string('redeemed_by', 64)->nullable()->after('accesses');
            $table->timestamp('redeemed_at')->nullable()->after('redeemed_by');
        });
        // orders kept before codes existed get theirs now
        foreach (\DB::table('clickbankjvzoo_orders')->whereNull('code')->pluck('id') as $id) {
            do {
                $code = Codes::generate();
            } while (\DB::table('clickbankjvzoo_orders')->where('code', $code)->exists());
            \DB::table('clickbankjvzoo_orders')->where('id', $id)->update(['code' => $code]);
        }
    }

    public function down()
    {
        Schema::table('clickbankjvzoo_orders', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'redeemed_by', 'redeemed_at']);
        });
    }
}
