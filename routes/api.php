<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 这是一个测试接口
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});
