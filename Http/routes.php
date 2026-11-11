<?php

// The sellers' notices: no login and no form token (FreeScout's "open" group). Each is checked against its seller's
// secret key instead.
Route::group(['middleware' => 'open', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\ClickBankJVZoo\Http\Controllers'], function () {
    Route::post('/clickbank/notice', 'NoticesController@clickbank')->name('clickbankjvzoo.clickbank');
    Route::post('/jvzoo/notice', 'NoticesController@jvzoo')->name('clickbankjvzoo.jvzoo');
});
