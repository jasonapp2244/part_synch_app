<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;

/**
 * The customer's side of services: see what a seller offers, ask for it,
 * follow the request, withdraw it while the vendor has not started.
 *
 * Payment is arranged with the vendor outside the app, as for orders.
 */
class ServiceRequestController extends Controller
{
    /** The columns the app's service card reads; nothing about the vendor. */
    private const PUBLIC_COLUMNS = [
        'id', 'user_id', 'product_id', 'title', 'description', 'price', 'service_types',
        'duration_type', 'service_mode', 'start_day', 'end_day', 'start_time', 'end_time',
        'is_recurring', 'status',
    ];

    /**
     * GET /user/vendor/{vendorId}/services — a seller's active services.
     */
    public function vendorServices($vendorId)
    {
        if (! self::approvedVendor($vendorId)) {
            return response()->json(['status' => false, 'message' => 'Seller not found.'], 404);
        }

        $services = Service::where('user_id', $vendorId)
            ->where('status', 'active')
            ->latest()
            ->get(self::PUBLIC_COLUMNS);

        return response()->json(['status' => true, 'data' => $services]);
    }

    /**
     * GET /user/service-requests — the customer's own requests, newest first.
     */
    public function index(Request $request)
    {
        $page = ServiceRequest::where('customer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $page->getCollection()->map->toApi()->values(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * POST /user/service-requests
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'preferred_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = $request->user();
        $service = Service::where('id', $data['service_id'])->where('status', 'active')->first();

        if (! $service || ! self::approvedVendor($service->user_id)) {
            return response()->json(['status' => false, 'message' => 'This service is no longer offered.'], 404);
        }

        if ((int) $service->user_id === (int) $customer->id) {
            return response()->json(['status' => false, 'message' => 'You cannot request your own service.'], 422);
        }

        if (UserBlock::existsBetween($customer->id, $service->user_id)) {
            return response()->json(['status' => false, 'message' => 'You cannot request a service from this seller.'], 403);
        }

        $open = ServiceRequest::where('customer_id', $customer->id)
            ->where('service_id', $service->id)
            ->whereIn('status', ServiceRequest::OPEN)
            ->exists();
        if ($open) {
            return response()->json([
                'status' => false,
                'message' => 'You already have an open request for this service. Check My service requests.',
            ], 409);
        }

        $serviceRequest = ServiceRequest::create([
            'request_number' => ServiceRequest::newNumber(),
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'vendor_id' => $service->user_id,
            'title' => $service->title,
            'price' => $service->price,
            'preferred_date' => $data['preferred_date'] ?? null,
            'address' => trim($data['address']),
            'phone' => trim($data['phone']),
            'note' => isset($data['note']) ? trim($data['note']) : null,
            'status' => 'requested',
        ]);

        $serviceRequest->notifyParty('requested', 'vendor');

        return response()->json([
            'status' => true,
            'message' => 'Request sent. The seller will accept it with a date, or let you know.',
            'data' => $serviceRequest->toApi(),
        ], 201);
    }

    /**
     * POST /user/service-requests/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $serviceRequest = ServiceRequest::where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (! $serviceRequest) {
            return response()->json(['status' => false, 'message' => 'Request not found.'], 404);
        }

        if (! in_array($serviceRequest->status, ServiceRequest::CUSTOMER_CANCELLABLE, true)) {
            return response()->json([
                'status' => false,
                'message' => 'This request is ' . strtolower($serviceRequest->statusLabel()) . ' and can no longer be cancelled here. Message the seller instead.',
            ], 422);
        }

        $serviceRequest->update([
            'status' => 'cancelled',
            'cancelled_by' => 'customer',
            'cancel_reason' => $data['reason'] ?? null,
        ]);

        $serviceRequest->notifyParty('cancelled', 'vendor');

        return response()->json([
            'status' => true,
            'message' => 'Request cancelled.',
            'data' => $serviceRequest->toApi(),
        ]);
    }

    /** A seller customers may deal with: an approved, active vendor account. */
    private static function approvedVendor($id): bool
    {
        return User::whereKey($id)
            ->where('role_id', 2)
            ->where('business_status', 'active')
            ->where('status', 'active')
            ->exists();
    }
}
