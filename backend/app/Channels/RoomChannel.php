<?php

namespace App\Channels;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Auth;

class RoomChannel
{
    public function join(Room $room): Channel|bool
    {
        if (!Auth::check()) {
            return false;
        }

        $participant = $room->participants()
            ->where('user_id', Auth::id())
            ->exists();

        if (!$participant) {
            return false;
        }

        return new Channel('room.' . $room->id);
    }
}
