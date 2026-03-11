<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'display_name' => 'required|string|max:50',
        ]);

        // Create a guest user
        $user = User::create([
            'display_name' => $request->display_name,
            'is_guest' => true,
            // email and jira_account_id are nullable now
        ]);

        // Create a token for the guest
        $token = $user->createToken('guest-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name,
                'is_guest' => true,
                'avatar' => null, // Guests don't have avatars initially
            ],
        ]);
    }
}
