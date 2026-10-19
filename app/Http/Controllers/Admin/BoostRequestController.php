<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorBoost;
use App\Services\BoostCapacity;
use App\Services\BoostWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin queue for product boosts: vendors request in the app, pay outside
 * it, and an admin records the payment and approves (or rejects).
 */
class BoostRequestController extends Controller
{
    public function __construct(private BoostWorkflow $workflow, private BoostCapacity $capacity)
    {
    }

    public function index(Request $request)
    {
        $with = ['vendor:id,first_name,last_name,email,phone_number', 'package', 'position', 'boostedProducts.product.productImages', 'approver:id,first_name,last_name'];

        $pending = VendorBoost::with($with)->where('approval_status', 'pending')->oldest()->get();

        // Earliest start each pending request could get, for the list and as
        // the approve form's default.
        $nextStart = $pending->mapWithKeys(function (VendorBoost $boost) {
            $next = $boost->position && $boost->package
                ? $this->capacity->nextAvailableStart($boost->position, $boost->boostedProducts->count(), (int) $boost->package->duration_days)
                : null;

            return [$boost->id => $next];
        });

        $current = VendorBoost::with($with)->current()->get();

        $history = VendorBoost::with($with)->history()->get();

        $stats = [
            'pending' => $pending->count(),
            'live' => $current->where('status', 'active')->count(),
            'scheduled' => $current->where('status', 'scheduled')->count(),
            'revenue_month' => (float) VendorBoost::whereIn('payment_status', ['success'])
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount'),
        ];

        $positions = BoostPosition::orderBy('priority')->get();
        $slotsInUse = $positions->mapWithKeys(fn ($p) => [$p->id => $this->capacity->inUseNow($p)]);

        return view('admin.boost_requests', [
            'pending' => $pending,
            'nextStart' => $nextStart,
            'current' => $current,
            'history' => $history,
            'stats' => $stats,
            'positions' => $positions,
            'slotsInUse' => $slotsInUse,
            'packages' => BoostPackage::where('status', true)->orderBy('price')->get(),
            'activePositions' => $positions->where('status', true)->values(),
            'vendors' => User::where('role_id', 2)->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']),
            'paymentMethods' => VendorBoost::PAYMENT_METHODS,
            'tab' => in_array($request->query('tab'), ['pending', 'current', 'history'], true) ? $request->query('tab') : 'pending',
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $boost = VendorBoost::findOrFail($id);
        $data = $this->validatePayment($request);

        try {
            $this->workflow->approve($boost, Auth::user(), $data, $this->startFrom($data));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('open_modal', "approve{$id}");
        }

        $boost->refresh();

        return redirect()->route('admin.boosts', ['tab' => 'current'])->with('success', $boost->status === 'scheduled'
            ? "Approved. {$boost->position?->name} is full until then, so it starts on {$boost->start_date->format('M j, Y H:i')}."
            : 'Approved. The boost is live now.');
    }

    public function reject(Request $request, int $id)
    {
        $boost = VendorBoost::findOrFail($id);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->workflow->reject($boost, Auth::user(), $data['reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.boosts')->with('success', 'Request rejected. The vendor has been notified.');
    }

    public function cancel(Request $request, int $id)
    {
        $boost = VendorBoost::findOrFail($id);
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
            'refunded' => 'nullable|boolean',
        ]);

        try {
            $this->workflow->cancel($boost, $data['reason'] ?? null, $request->boolean('refunded'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.boosts', ['tab' => 'history'])->with('success', 'Boost cancelled and its slots freed.');
    }

    /**
     * Boost sold directly (phone, email, in person): create and approve in one step.
     */
    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role_id', 2)],
            'package_id' => 'required|integer|exists:boost_packages,id',
            'position_id' => 'required|integer|exists:boost_positions,id',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|distinct',
        ]);
        $payment = $this->validatePayment($request);

        try {
            $boost = $this->workflow->request(
                User::findOrFail($request->vendor_id),
                BoostPackage::findOrFail($request->package_id),
                BoostPosition::findOrFail($request->position_id),
                $request->product_ids,
                null,
                false
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('open_modal', 'createBoost');
        }

        try {
            $this->workflow->approve($boost, Auth::user(), $payment, $this->startFrom($payment));
        } catch (ValidationException $e) {
            // Keep the request so nothing typed is lost; it waits in Pending.
            return redirect()->route('admin.boosts')->withErrors(array_merge(
                ['created' => 'The boost was created but not approved, so it is waiting in Pending.'],
                $e->errors()
            ));
        }

        return redirect()->route('admin.boosts', ['tab' => 'current'])->with('success', 'Boost created and approved.');
    }

    /**
     * The chosen vendor's active products, for the create form.
     */
    public function vendorProducts(int $vendorId)
    {
        $products = Product::where('user_id', $vendorId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($products);
    }

    private function validatePayment(Request $request): array
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(VendorBoost::PAYMENT_METHODS))],
            'payment_reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0|max:99999999',
            'start_date' => 'nullable|date',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        return [
            'method' => $data['payment_method'],
            'reference' => $data['payment_reference'] ?? null,
            'amount' => $data['amount'],
            'note' => $data['admin_note'] ?? null,
            'start_date' => $data['start_date'] ?? null,
        ];
    }

    private function startFrom(array $payment): Carbon
    {
        return $payment['start_date'] ? Carbon::parse($payment['start_date']) : now();
    }
}
