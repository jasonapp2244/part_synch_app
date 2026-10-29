<?php

namespace App\Http\Controllers;

use App\Models\ChMessage;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reporting and blocking for user generated content.
 *
 * App Store guideline 1.2 requires any app with user generated content — this
 * one has in-app chat, product listings and reviews — to ship a way to report
 * offensive content, a way to block abusive users, and a published contact for
 * complaints. Reports land in the admin panel's moderation queue; blocks are
 * enforced by the chat controller.
 */
class SafetyController extends Controller
{
    /**
     * The reason list the app shows in its report sheet, so the client does
     * not have to hard-code strings the server might change.
     */
    public function reportReasons()
    {
        return response()->json([
            'status' => true,
            'message' => 'Report reasons fetched.',
            'data' => [
                ['value' => 'spam', 'label' => 'Spam or misleading'],
                ['value' => 'harassment', 'label' => 'Harassment or bullying'],
                ['value' => 'hate_speech', 'label' => 'Hate speech'],
                ['value' => 'sexual_content', 'label' => 'Sexual or explicit content'],
                ['value' => 'violence', 'label' => 'Violence or threats'],
                ['value' => 'scam_or_fraud', 'label' => 'Scam or fraud'],
                ['value' => 'counterfeit', 'label' => 'Counterfeit or illegal part'],
                ['value' => 'other', 'label' => 'Something else'],
            ],
        ]);
    }

    /**
     * Report a user, product, chat message, review or service.
     */
    public function report(Request $request)
    {
        $request->validate([
            'reportable_type' => ['required', Rule::in(['user', 'product', 'message', 'review', 'service'])],
            // A string, not an integer: chat message ids are UUIDs.
            'reportable_id' => ['required', 'string', 'max:64'],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $reporter = $request->user();

        if (! $this->subjectExists($request->reportable_type, $request->reportable_id)) {
            return response()->json([
                'status' => false,
                'message' => 'The content you are reporting no longer exists.',
            ], 404);
        }

        if ($request->reportable_type === 'user' && (int) $request->reportable_id === (int) $reporter->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot report yourself.',
            ], 422);
        }

        // One open report per person per item — re-reporting the same thing
        // would just flood the moderation queue.
        $existing = Report::where('reporter_id', $reporter->id)
            ->where('reportable_type', $request->reportable_type)
            ->where('reportable_id', $request->reportable_id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => true,
                'message' => 'You have already reported this. Our team is reviewing it.',
                'data' => $existing,
            ]);
        }

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $request->reportable_type,
            'reportable_id' => $request->reportable_id,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thank you. Our team will review this within 24 hours.',
            'data' => $report,
        ], 201);
    }

    /**
     * Reports this user has filed, so the app can show their status.
     */
    public function myReports(Request $request)
    {
        $reports = Report::where('reporter_id', $request->user()->id)
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => true,
            'message' => 'Your reports fetched.',
            'data' => $reports,
        ]);
    }

    /**
     * Block another user. Takes effect in both directions immediately.
     */
    public function block(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $me = $request->user();

        if ((int) $request->user_id === (int) $me->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot block yourself.',
            ], 422);
        }

        $block = UserBlock::firstOrCreate(
            ['blocker_id' => $me->id, 'blocked_id' => $request->user_id],
            ['reason' => $request->reason]
        );

        return response()->json([
            'status' => true,
            'message' => 'User blocked. They can no longer message you.',
            'data' => $block,
        ]);
    }

    /**
     * Unblock a user this account previously blocked.
     */
    public function unblock(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $deleted = UserBlock::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $request->user_id)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'status' => false,
                'message' => 'That user is not blocked.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'User unblocked.',
        ]);
    }

    /**
     * The list behind the app's "Blocked users" settings screen.
     */
    public function blockedUsers(Request $request)
    {
        $blocks = UserBlock::with(['blocked:id,first_name,last_name,profile_image,business_name'])
            ->where('blocker_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn ($block) => [
                'id' => $block->id,
                'user_id' => $block->blocked_id,
                'name' => $block->blocked
                    ? trim($block->blocked->first_name . ' ' . $block->blocked->last_name)
                    : 'Deleted user',
                'business_name' => $block->blocked->business_name ?? null,
                'profile_image' => $block->blocked->profile_image ?? null,
                'reason' => $block->reason,
                'blocked_at' => $block->created_at,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Blocked users fetched.',
            'data' => $blocks,
        ]);
    }

    private function subjectExists(string $type, $id): bool
    {
        return match ($type) {
            'user' => User::whereKey($id)->exists(),
            'product' => Product::whereKey($id)->exists(),
            'message' => ChMessage::whereKey($id)->exists(),
            'review' => Review::whereKey($id)->exists(),
            'service' => Service::whereKey($id)->exists(),
            default => false,
        };
    }
}
