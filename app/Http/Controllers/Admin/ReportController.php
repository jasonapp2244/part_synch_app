<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChMessage;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Moderation queue for abuse reports.
 *
 * App Store review asks not just for a report button but for evidence that
 * reports are acted on within 24 hours. This screen is that evidence: every
 * report has a status, an admin note and an auditable reviewer.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $reports = Report::with('reporter:id,first_name,last_name,email')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get()
            ->each(function ($report) {
                // Resolved lazily so a report whose target was deleted still
                // renders instead of blowing up the page.
                $report->subject_label = $report->subjectLabel();
            });

        $counts = [
            'pending' => Report::where('status', 'pending')->count(),
            'reviewing' => Report::where('status', 'reviewing')->count(),
            'actioned' => Report::where('status', 'actioned')->count(),
            'dismissed' => Report::where('status', 'dismissed')->count(),
        ];

        return view('admin.view_reports', compact('reports', 'counts', 'status'));
    }

    /**
     * Record a moderation decision.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending', 'reviewing', 'actioned', 'dismissed'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = Report::findOrFail($id);

        $report->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report updated.');
    }

    /**
     * Take the reported content down and mark the report actioned in one step.
     */
    public function takeDown(Request $request, $id)
    {
        $report = Report::findOrFail($id);

        $done = match ($report->reportable_type) {
            'user' => (bool) User::whereKey($report->reportable_id)->update(['status' => 'inactive']),
            'product' => (bool) Product::whereKey($report->reportable_id)->update(['status' => 'inactive']),
            'review' => (bool) Review::whereKey($report->reportable_id)->update(['status' => 'rejected']),
            'message' => (bool) ChMessage::whereKey($report->reportable_id)->delete(),
            'service' => (bool) Service::whereKey($report->reportable_id)->update(['status' => 'inactive']),
            default => false,
        };

        if (! $done) {
            return back()->with('error', 'The reported content no longer exists.');
        }

        $report->update([
            'status' => 'actioned',
            'admin_note' => trim(($report->admin_note ? $report->admin_note . ' | ' : '')
                . 'Content taken down by admin.'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Content removed and report marked as actioned.');
    }
}
