<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return response()->json(['message' => 'AhaTask API is Up and Running!']);
});
