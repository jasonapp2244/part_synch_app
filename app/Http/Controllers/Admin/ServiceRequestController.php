<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

/**
 * Every service request between customers and vendors. The admin watches and,
 * when needed, cancels an open one; both sides are told why.
 */
class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        if ($status && ! array_key_exists($status, ServiceRequest::STATUS_LABELS)) {
            $status = null;
        }

        $requests = ServiceRequest::with(['customer:id,first_name,last_name,email', 'vendor:id,first_name,last_name,business_name,email'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        $counts = [];
        foreach (array_keys(ServiceRequest::STATUS_LABELS) as $key) {
            $counts[$key] = ServiceRequest::where('status', $key)->count();
        }

        return view('admin.view_service_requests', compact('requests', 'counts', 'status'));
    }

    public function cancel(Request $request, $id)
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ]);

        $serviceRequest = ServiceRequest::findOrFail($id);

        if (! $serviceRequest->isOpen()) {
            return back()->with('error', "{$serviceRequest->request_number} is already {$serviceRequest->statusLabel()} and cannot be cancelled.");
        }

        $serviceRequest->update([
            'status' => 'cancelled',
            'cancelled_by' => 'admin',
            'cancel_reason' => $data['cancel_reason'],
        ]);

        $serviceRequest->notifyParty('cancelled', 'customer');
        $serviceRequest->notifyParty('cancelled', 'vendor');

        return back()->with('success', "{$serviceRequest->request_number} cancelled. The customer and the vendor were told why.");
    }
}
