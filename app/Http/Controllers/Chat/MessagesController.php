<?php

namespace App\Http\Controllers\Chat;

use Chatify\Http\Controllers\Api\MessagesController as ChatifyMessagesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Models\User;
use App\Models\UserBlock;
use Chatify\Facades\ChatifyMessenger as Chatify;

class MessagesController extends ChatifyMessagesController
{
    /**
     * Override: refuse to deliver a message when either party has blocked the
     * other. App Store guideline 1.2 requires blocking to actually stop
     * contact, so it is enforced here rather than only hidden in the UI.
     */
    public function send(Request $request)
    {
        $recipient = $request['id'];

        if ($recipient && UserBlock::existsBetween(Auth::id(), $recipient)) {
            return Response::json([
                'status' => 0,
                'message' => 'You can no longer send messages to this user.',
            ], 403);
        }

        return parent::send($request);
    }

    /**
     * Fields a contact row may carry. Chatify selects users.* and hydrates the
     * rows into ChMessage, so User::$hidden never applies and the password
     * hash, OTP and reset tokens would otherwise be sent to the other party
     * (BCR-35). These are the fields the app reads.
     */
    private const CONTACT_FIELDS = [
        'id', 'first_name', 'last_name', 'business_name', 'profile_image', 'avatar', 'role_id',
    ];

    /**
     * Override: expose only public contact fields and keep blocked users out
     * of the contacts list.
     */
    public function getContacts(Request $request)
    {
        $data = parent::getContacts($request)->getData(true);
        $blockedIds = UserBlock::idsFor(Auth::id());
        $allowed = array_flip(self::CONTACT_FIELDS);

        $contacts = is_array($data['contacts'] ?? null) ? $data['contacts'] : [];
        $data['contacts'] = array_values(array_map(
            fn ($contact) => array_intersect_key($contact, $allowed),
            array_filter($contacts, fn ($contact) => ! in_array($contact['id'] ?? null, $blockedIds))
        ));

        return Response::json($data, 200);
    }

    /**
     * Override: Search users by first_name/last_name instead of 'name'.
     */
    public function search(Request $request)
    {
        $input = trim(filter_var($request['input']));

        $records = User::where('id', '!=', Auth::user()->id)
            ->whereNotIn('id', UserBlock::idsFor(Auth::id()))
            ->where(function ($q) use ($input) {
                $q->where('first_name', 'LIKE', "%{$input}%")
                  ->orWhere('last_name', 'LIKE', "%{$input}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$input}%"]);
            })
            ->paginate($request->per_page ?? $this->perPage);

        $items = [];
        foreach ($records->items() as $record) {
            $userData = Chatify::getUserWithAvatar($record);
            $items[] = $userData;
        }

        return Response::json([
            'records' => $items,
            'total' => $records->total(),
            'last_page' => $records->lastPage(),
        ], 200);
    }

    /**
     * Override: Validate user_id before starring to prevent null constraint violation.
     */
    public function favorite(Request $request)
    {
        $userId = $request['user_id'];

        if (!$userId) {
            return Response::json([
                'status' => 0,
                'message' => 'user_id is required',
            ], 422);
        }

        // Verify user exists
        if (!User::where('id', $userId)->exists()) {
            return Response::json([
                'status' => 0,
                'message' => 'User not found',
            ], 404);
        }

        $favoriteStatus = Chatify::inFavorite($userId) ? 0 : 1;
        Chatify::makeInFavorite($userId, $favoriteStatus);

        return Response::json([
            'status' => $favoriteStatus,
        ], 200);
    }
}
