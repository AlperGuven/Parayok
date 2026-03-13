<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastingAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        \Illuminate\Support\Facades\Log::info("Broadcasting auth hit (Controller). User: " . $request->user()?->id . " Channel: " . $request->input('channel_name'));
        return Broadcast::auth($request);
    }
}
