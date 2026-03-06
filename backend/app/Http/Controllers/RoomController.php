<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return [];
        }

        $rooms = Room::whereHas('participants', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })->withCount('participants')->get();

        return $rooms->map(function ($room) {
            return [
                'id' => $room->id,
                'uuid' => $room->uuid,
                'name' => $room->name,
                'status' => $room->status,
                'participant_count' => $room->participants_count,
            ];
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $room = Room::create([
            'uuid' => Str::uuid()->toString(),
            'name' => $request->name,
            'created_by' => $user->id,
            'status' => 'waiting',
            'estimation_scale' => json_encode(["1","2","3","5","8","13","21","?","☕"]),
        ]);

        RoomParticipant::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'moderator',
            'is_online' => true,
        ]);

        return response()->json([
            'id' => $room->id,
            'uuid' => $room->uuid,
            'name' => $room->name,
            'status' => $room->status,
        ], 201);
    }

    public function show(Request $request, string $uuid)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $room = Room::where('uuid', $uuid)->with(['issues', 'roomParticipants.user', 'creator'])->firstOrFail();
        
        $isParticipant = $room->participants()->where('user_id', $user->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant of this room'], 403);
        }

        return [
            'id' => $room->id,
            'uuid' => $room->uuid,
            'name' => $room->name,
            'status' => $room->status,
            'created_by' => $room->created_by,
            'creator_name' => $room->creator->display_name,
            'estimation_scale' => json_decode($room->estimation_scale ?? '[]'),
            'issues' => $room->issues->map(function ($issue) {
                return [
                    'id' => $issue->id,
                    'jira_issue_key' => $issue->jira_issue_key,
                    'summary' => $issue->summary,
                    'description' => $issue->description,
                    'jira_url' => $issue->jira_url,
                    'status' => $issue->status,
                    'final_score' => $issue->final_score,
                    'sort_order' => $issue->sort_order,
                ];
            }),
            'participants' => $room->roomParticipants->map(function ($participant) {
                return [
                    'id' => $participant->id,
                    'user_id' => $participant->user_id,
                    'display_name' => $participant->user->display_name,
                    'avatar_url' => $participant->user->avatar_url,
                    'role' => $participant->role,
                    'is_online' => $participant->is_online,
                ];
            }),
        ];
    }

    public function join(Request $request, string $uuid)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        $existingParticipant = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingParticipant) {
            $existingParticipant->update(['is_online' => true]);
            return response()->json(['message' => 'Already joined']);
        }

        RoomParticipant::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'voter',
            'is_online' => true,
        ]);

        return response()->json(['message' => 'Joined successfully']);
    }

    public function complete(Request $request, string $uuid)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        
        $moderator = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->where('role', 'moderator')
            ->exists();

        if (!$moderator) {
            return response()->json(['message' => 'Only moderator can complete the room'], 403);
        }

        $room->update(['status' => 'completed']);

        return response()->json(['message' => 'Room completed']);
    }

    public function reopen(Request $request, string $uuid)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        
        $moderator = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->where('role', 'moderator')
            ->exists();

        if (!$moderator) {
            return response()->json(['message' => 'Only moderator can reopen the room'], 403);
        }

        $room->update(['status' => 'active']); // Or 'waiting'? active is fine.

        return response()->json(['message' => 'Room reopened']);
    }

    public function destroy(Request $request, string $uuid)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        
        $moderator = $room->participants()
            ->where('user_id', $request->user()->id)
            ->where('role', 'moderator')
            ->exists();

        if (!$moderator) {
            return response()->json(['message' => 'Only moderator can delete the room'], 403);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted']);
    }
}
