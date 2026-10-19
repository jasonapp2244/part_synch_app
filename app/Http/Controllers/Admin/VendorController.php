<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\VendorApprovalMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VendorController extends Controller
{
    public function VendorRecords()
    {
        // Vendors waiting for approval first, so a new application is not
        // buried on the last page.
        $vendors = User::where('role_id', 2)
            ->orderByRaw("business_status = 'active'")
            ->orderBy('id')
            ->get();
        $pendingCount = $vendors->where('business_status', '!=', 'active')->count();

        return view('admin.view_vendor_records', compact('vendors', 'pendingCount'));
    }

    /**
     * Approve a vendor's business account, or withdraw the approval. The app
     * only lets a vendor in once `business_status` is active; `status` is the
     * separate block switch.
     */
    public function toggleApproval($id)
    {
        $vendor = User::where('role_id', 2)->findOrFail($id);
        $approved = $vendor->business_status !== 'active';

        $vendor->business_status = $approved ? 'active' : 'inactive';
        $vendor->save();

        if (! $approved) {
            // Signed-in vendor sessions would otherwise keep working.
            $vendor->tokens()->delete();
        }

        $this->notifyVendor($vendor, $approved);

        return redirect()->route('vendor.records')->with('success', $approved
            ? "{$vendor->email} approved. They can now sign in to the app."
            : "Approval withdrawn for {$vendor->email}.");
    }

    private function notifyVendor(User $vendor, bool $approved): void
    {
        [$subject, $body] = VendorApprovalMail::summary($approved);

        try {
            Notification::create([
                'vendor_id' => $vendor->id,
                'email_subject' => $subject,
                'email_body' => $body,
                'status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::error('Vendor approval notification row failed', ['vendor_id' => $vendor->id, 'error' => $e->getMessage()]);
        }

        if ($vendor->email) {
            try {
                Mail::to($vendor->email)->send(new VendorApprovalMail($vendor, $approved));
            } catch (\Throwable $e) {
                Log::error('Vendor approval email failed', ['vendor_id' => $vendor->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function toggleStatus($id)
    {
        $vendor = User::where('role_id', 2)->findOrFail($id);
        $vendor->status = $vendor->status === 'active' ? 'inactive' : 'active';
        $vendor->save();

        // Blocking also ends their app sessions; signin refuses them after.
        if ($vendor->status !== 'active') {
            $vendor->tokens()->delete();
        }

        return redirect()->route('vendor.records')->with('success', 'Vendor status updated to ' . $vendor->status . '.');
    }

    public function destroy($id)
    {
        $vendor = User::where('role_id', 2)->findOrFail($id);
        $vendor->delete();

        return redirect()->route('vendor.records')->with('success', 'Vendor deleted successfully.');
    }
}
