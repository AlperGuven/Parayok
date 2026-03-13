<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Room;
use App\Models\RoomParticipant;

Broadcast::channel('room.{id}', function ($user, $id) {
    \Illuminate\Support\Facades\Log::info("Channel auth attempt: User {$user->id}, Room {$id}");
    return checkRoomAccess($user, $id);
});

// Removed duplicate presence-room rule because Laravel handles prefix automatically

if (!function_exists('checkRoomAccess')) {
    function checkRoomAccess($user, $id) {
        $participant = RoomParticipant::where('room_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            \Illuminate\Support\Facades\Log::info("Channel auth success");
            return [
                'id' => $user->id,
                'user_id' => $user->id,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'role' => $participant->role,
                'is_online' => true
            ];
        }
        
        return false;
    }
}