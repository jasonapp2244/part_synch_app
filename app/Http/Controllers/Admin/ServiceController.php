<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;

/**
 * Every vendor's services, so the admin can see what is offered and switch
 * off anything that should not be. Vendors manage their own in the app.
 */
class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::with(['user:id,first_name,last_name,business_name,email', 'product:id,name'])
            ->withCount('requests')
            ->latest()
            ->get();

        return view('admin.view_services', compact('services'));
    }

    public function toggleStatus($id)
    {
        $service = Service::findOrFail($id);
        $service->status = $service->status === 'active' ? 'inactive' : 'active';
        $service->save();

        return back()->with('success', $service->status === 'active'
            ? "\"{$service->title}\" is offered to customers again."
            : "\"{$service->title}\" is switched off. Customers no longer see it.");
    }
}
