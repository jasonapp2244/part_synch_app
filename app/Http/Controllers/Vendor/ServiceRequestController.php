<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The vendor's side of service requests: see what customers asked for and
 * move each request on. Mirrors Vendor\OrderController::orderManage — scoped
 * to the signed-in vendor, forward-only moves, the customer told every time.
 */
class ServiceRequestController extends Controller
{
    /**
     * GET /vendor/service-requests?status= — an empty list is 200 with [],
     * not the 400 the orders list answers.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');

        $page = ServiceRequest::where('vendor_id', $request->user()->id)
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $page->getCollection()->map->toApi()->values(),
            'open_count' => ServiceRequest::where('vendor_id', $request->user()->id)
                ->whereIn('status', ServiceRequest::OPEN)
                ->count(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * POST /vendor/service-requests/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined', 'in_progress', 'completed', 'cancelled'])],
            'scheduled_at' => ['required_if:status,accepted', 'nullable', 'date_format:Y-m-d H:i', 'after_or_equal:today'],
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'decline_reason' => ['required_if:status,declined', 'nullable', 'string', 'max:500'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $serviceRequest = DB::transaction(function () use ($request, $id, $data) {
            $serviceRequest = ServiceRequest::where('id', $id)
                ->where('vendor_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $serviceRequest) {
                return null;
            }

            $current = $serviceRequest->status;
            $allowed = ServiceRequest::VENDOR_FLOW[$current] ?? [];
            if (! in_array($data['status'], $allowed, true)) {
                return ['refused' => $current, 'allowed' => $allowed];
            }

            $changes = ['status' => $data['status']];
            if ($data['status'] === 'accepted') {
                $changes['scheduled_at'] = $data['scheduled_at'];
                if (array_key_exists('quoted_price', $data) && $data['quoted_price'] !== null) {
                    $changes['quoted_price'] = $data['quoted_price'];
                }
            }
            if ($data['status'] === 'declined') {
                $changes['decline_reason'] = $data['decline_reason'];
            }
            if ($data['status'] === 'cancelled') {
                $changes['cancelled_by'] = 'vendor';
                $changes['cancel_reason'] = $data['cancel_reason'] ?? null;
            }

            $serviceRequest->update($changes);

            return $serviceRequest;
        });

        if ($serviceRequest === null) {
            return response()->json(['status' => false, 'message' => 'Request not found.'], 404);
        }

        if (is_array($serviceRequest)) {
            $current = $serviceRequest['refused'];
            $allowed = $serviceRequest['allowed'];
            $label = ServiceRequest::STATUS_LABELS[$current] ?? $current;

            return response()->json([
                'status' => false,
                'message' => $allowed
                    ? "A request that is {$label} cannot be moved to " . (ServiceRequest::STATUS_LABELS[$data['status']] ?? $data['status']) . '.'
                    : "This request is {$label} and can no longer be changed.",
                'current_status' => $current,
                'allowed_statuses' => $allowed,
            ], 422);
        }

        // Told after the commit: a failing mail server must not undo the move.
        $serviceRequest->notifyParty($serviceRequest->status, 'customer');

        return response()->json([
            'status' => true,
            'message' => 'Request updated.',
            'data' => $serviceRequest->toApi(),
        ]);
    }

    /**
     * POST /vendor/service-requests/{id}/paid — the customer paid outside the
     * app. Allowed once the request is accepted, and only once.
     */
    public function markPaid(Request $request, $id)
    {
        $serviceRequest = ServiceRequest::where('id', $id)
            ->where('vendor_id', $request->user()->id)
            ->first();

        if (! $serviceRequest) {
            return response()->json(['status' => false, 'message' => 'Request not found.'], 404);
        }

        if (! in_array($serviceRequest->status, ['accepted', 'in_progress', 'completed'], true) || $serviceRequest->paid_outside) {
            return response()->json([
                'status' => false,
                'message' => $serviceRequest->paid_outside
                    ? 'Payment is already recorded for this request.'
                    : 'Accept the request before recording a payment.',
            ], 422);
        }

        $serviceRequest->update(['paid_outside' => true]);
        $serviceRequest->notifyParty('paid', 'customer');

        return response()->json([
            'status' => true,
            'message' => 'Payment recorded.',
            'data' => $serviceRequest->toApi(),
        ]);
    }
}
