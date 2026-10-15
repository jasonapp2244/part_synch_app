<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\BoostedProduct;
use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\Product;
use App\Models\VendorBoost;
use App\Services\BoostCapacity;
use App\Services\BoostWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Vendor side of product boosts.
 *
 * The vendor requests a boost in the app; payment happens outside the app
 * (the admin sends an invoice or payment link) and an admin approves it in
 * the panel. There is deliberately no card checkout here: Apple 3.1.1 and
 * Google Play billing reject a card gateway for a digital promotion.
 */
class VendorBoostController extends Controller
{
    public function __construct(private BoostWorkflow $workflow, private BoostCapacity $capacity)
    {
    }

    /**
     * Get all available boost packages
     */
    public function getBoostPackages()
    {
        $packages = BoostPackage::where('status', true)
            ->orderBy('price', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Boost packages fetched successfully',
            'data' => $packages
        ]);
    }

    /**
     * Get all packages with position-wise pricing
     */
    public function getBoostPricing()
    {
        $packages = BoostPackage::where('status', true)->orderBy('price', 'asc')->get();
        $positions = BoostPosition::where('status', true)->orderBy('priority', 'asc')->get();

        $data = $packages->map(function ($package) use ($positions) {
            return [
                'package_id' => $package->id,
                'package' => $package->name,
                'duration_days' => $package->duration_days,
                'product_limit' => $package->product_limit,
                'base_price' => $package->price,
                'currency' => $package->currency,
                'positions' => $positions->map(function ($position) use ($package) {
                    return [
                        'position_id' => $position->id,
                        'name' => $position->name,
                        'price_multiplier' => $position->price_multiplier,
                        'final_price' => BoostWorkflow::price($package, $position),
                    ];
                })
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Boost pricing fetched successfully',
            'data' => $data
        ]);
    }

    /**
     * Price and earliest start for a package + position (+ product count).
     */
    public function getPricePreview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:boost_packages,id',
            'position_id' => 'required|exists:boost_positions,id',
            'product_count' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $package = BoostPackage::find($request->package_id);
        $position = BoostPosition::find($request->position_id);
        $count = (int) ($request->product_count ?: 1);
        $next = $this->capacity->nextAvailableStart($position, $count, (int) $package->duration_days);

        return response()->json([
            'status' => true,
            'message' => 'Price preview',
            'data' => [
                'package_name' => $package->name,
                'position_name' => $position->name,
                'base_price' => $package->price,
                'price_multiplier' => $position->price_multiplier,
                'final_price' => BoostWorkflow::price($package, $position),
                'duration_days' => $package->duration_days,
                'product_limit' => min((int) $package->product_limit, (int) $position->display_limit),
                'currency' => $package->currency,
                'next_available_date' => $next?->toIso8601String(),
                'available_now' => $next !== null && $next->lte(now()->addMinute()),
            ]
        ]);
    }

    /**
     * Active boost positions, each with when one product could start.
     */
    public function getBoostPositions()
    {
        $positions = BoostPosition::where('status', true)
            ->orderBy('priority', 'asc')
            ->get()
            ->map(function (BoostPosition $position) {
                $next = $this->capacity->nextAvailableStart($position, 1, 1);

                return array_merge($position->toArray(), [
                    'slots_in_use' => $this->capacity->inUseNow($position),
                    'next_available_date' => $next?->toIso8601String(),
                    'available_now' => $next !== null && $next->lte(now()->addMinute()),
                ]);
            });

        return response()->json([
            'status' => true,
            'message' => 'Boost positions fetched successfully',
            'data' => $positions
        ]);
    }

    /**
     * Request a boost. Payment is arranged outside the app; an admin approves.
     */
    public function requestBoost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|integer|exists:boost_packages,id',
            'position_id' => 'required|integer|exists:boost_positions,id',
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => 'integer|distinct',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $boost = $this->workflow->request(
                Auth::user(),
                BoostPackage::findOrFail($request->package_id),
                BoostPosition::findOrFail($request->position_id),
                $request->product_ids,
                $request->note
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Request sent. Our team will contact you by email to complete it.',
            'data' => $this->present($boost->fresh()),
        ], 201);
    }

    /**
     * All of the vendor's boost requests, newest first.
     */
    public function getMyBoosts()
    {
        $boosts = VendorBoost::where('vendor_id', Auth::id())
            ->with(['package', 'position', 'boostedProducts.product.productImages'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (VendorBoost $boost) => $this->present($boost));

        return response()->json([
            'status' => true,
            'message' => 'Your boosts fetched successfully',
            'data' => $boosts
        ]);
    }

    /**
     * Withdraw a request that has not been approved yet.
     */
    public function cancelBoostPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_boost_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $boost = VendorBoost::where('id', $request->vendor_boost_id)
            ->where('vendor_id', Auth::id())
            ->first();

        if (! $boost) {
            return response()->json(['status' => false, 'message' => 'Boost request not found.'], 404);
        }

        if ($boost->approval_status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Only a pending request can be withdrawn. Contact support about an approved boost.'
            ], 400);
        }

        $this->workflow->cancel($boost, 'Withdrawn by vendor', false, true);

        return response()->json([
            'status' => true,
            'message' => 'Boost request withdrawn.'
        ]);
    }

    /**
     * Get vendor's boosted products that are showing right now
     */
    public function getMyBoostedProducts()
    {
        $vendorId = Auth::id();

        $boostedProducts = BoostedProduct::live()
            ->whereHas('vendorBoost', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with(['product.productImages', 'vendorBoost.package', 'vendorBoost.position'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Boosted products fetched successfully',
            'data' => $boostedProducts
        ]);
    }

    /**
     * Stop the live boost on some of the vendor's products early.
     */
    public function deleteBoost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $vendor = Auth::user();

        $productIds = Product::whereIn('id', $request->product_ids)
            ->where('user_id', $vendor->id)
            ->pluck('id')
            ->all();

        if (count($productIds) !== count(array_unique($request->product_ids))) {
            return response()->json([
                'status' => false,
                'message' => 'Some products do not belong to you'
            ], 400);
        }

        $running = BoostedProduct::whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->where('boost_end', '>', now())
            ->whereHas('vendorBoost', fn ($q) => $q->approvedAndPaid()->where('vendor_id', $vendor->id))
            ->get();

        if ($running->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No active boosts found for these products'
            ], 404);
        }

        try {
            DB::transaction(function () use ($running, $productIds) {
                BoostedProduct::whereIn('id', $running->pluck('id'))->update(['is_active' => false]);

                foreach ($running->pluck('vendor_boost_id')->unique() as $boostId) {
                    $left = BoostedProduct::where('vendor_boost_id', $boostId)->where('is_active', true)->exists();
                    if (! $left) {
                        VendorBoost::where('id', $boostId)->update(['is_active' => false]);
                    }
                }

                $this->workflow->clearTopFlags($productIds);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to stop boost', ['vendor_id' => $vendor->id, 'error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to stop the boost. Please try again.'
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Boost stopped for ' . $running->count() . ' product(s).',
            'deleted_count' => $running->count(),
            'product_ids' => $productIds
        ]);
    }

    /**
     * The shape the app reads for one boost.
     */
    private function present(VendorBoost $boost): array
    {
        $boost->loadMissing(['package', 'position', 'boostedProducts.product.productImages']);

        return [
            'id' => $boost->id,
            'status' => $boost->status,
            'package' => $boost->package ? [
                'id' => $boost->package->id,
                'name' => $boost->package->name,
                'duration_days' => $boost->package->duration_days,
            ] : null,
            'position' => $boost->position ? [
                'id' => $boost->position->id,
                'name' => $boost->position->name,
            ] : null,
            'start_date' => $boost->start_date?->toIso8601String(),
            'end_date' => $boost->end_date?->toIso8601String(),
            'requested_at' => $boost->created_at?->toIso8601String(),
            'note' => $boost->vendor_note,
            'rejection_reason' => in_array($boost->approval_status, ['rejected', 'cancelled'], true) ? $boost->rejection_reason : null,
            'products' => $boost->boostedProducts->map(fn ($item) => [
                'id' => $item->product_id,
                'name' => $item->product?->name,
                'image_url' => $item->product?->productImages?->first()?->image_url,
            ])->values(),
        ];
    }
}
