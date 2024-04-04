<?php

use Illuminate\Support\Facades\Route;
use Modules\FileManagerCore\LfmController;

Route::get('/', function () {
    return view('welcome');
});

auth()->loginUsingId(1);

Route::group(['prefix' => 'filemanager', 'middleware' => ['web', 'auth']], function () {
    Route::controller(LfmController::class)->name('unisharp.lfm.')->group(function () {
        Route::get('/demo', 'demo');

        // display main layout
        Route::get('/', 'show')->name('show');

        // display integration error messages
        Route::get('/errors', 'getErrors')->name('getErrors');

        // upload
        Route::any('/upload', 'upload')->name('upload');

        // list images & files
        Route::get('/jsonitems', 'getItems')->name('getItems');

        Route::get('/move', 'move')->name('move');
        Route::get('/domove', 'doMove')->name('doMove');

        // folders
        Route::get('/newfolder', 'getAddfolder')->name('getAddfolder');

        // list folders
        Route::get('/folders', 'getFolders')->name('getFolders');

        // crop
        Route::get('/crop', 'getCrop')->name('getCrop');
        Route::get('/cropimage', 'getCropImage')->name('getCropImage');
        Route::get('/cropnewimage', 'getNewCropImage')->name('getNewCropImage');

        // rename
        Route::get('/rename', 'getRename')->name('getRename');

        // scale/resize
        Route::get('/resize', 'getResize')->name('getResize');
        Route::get('/doresize', 'performResize')->name('performResize');
        Route::get('/doresizenew', 'performResizeNew')->name('performResizeNew');

        // download
        Route::get('/download', 'getDownload')->name('getDownload');

        // delete
        Route::get('/delete', 'getDelete')->name('getDelete');
    });
});
