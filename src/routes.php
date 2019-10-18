<?php

Route::group(['prefix' => 'message', 'namespace' => "\Cmgmyr\Messenger\Controllers", 'middleware' => ['web']], function () {
    Route::Post('/send', 'Messenger@store')->name('message.store');
    Route::Post('/get', 'Messenger@show')->name('message.show');
    Route::Post('/last/get', 'Messenger@showLast')->name('message.showLast');
});
