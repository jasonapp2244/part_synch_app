<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Product and vendor ratings.
 *
 * The reviews table and model already existed but nothing read or wrote them,
 * so ratings never reached the app. New reviews are auto-approved when they
 * come from a verified purchase and held for moderation otherwise, which keeps
 * the listing pages honest without making a human approve every one.
 */
class ReviewController extends Controller
{
    /**
     * Approved reviews for one product, plus the rating breakdown the product
     * page renders as a star histogram.
     */
    public function productReviews(Request $request, $productId)
    {
        if (! Product::whereKey($productId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $reviews = Review::approved()
            ->where('product_id', $productId)
            ->with(['user:id,first_name,last_name,profile_image'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => true,
            'message' => 'Reviews fetched successfully.',
            'data' => $reviews,
            'summary' => $this->summaryFor('product_id', $productId),
        ]);
    }

    /**
     * Approved reviews for a vendor, aggregated across all their products.
     */
    public function vendorReviews(Request $request, $vendorId)
    {
        if (! User::whereKey($vendorId)->where('role_id', 2)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Vendor not found.',
            ], 404);
        }

        $reviews = Review::approved()
            ->where('vendor_id', $vendorId)
            ->with(['user:id,first_name,last_name,profile_image', 'product:id,name'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => true,
            'message' => 'Vendor reviews fetched successfully.',
            'data' => $reviews,
            'summary' => $this->summaryFor('vendor_id', $vendorId),
        ]);
    }

    /**
     * Reviews written by the signed-in user, including ones still pending, so
     * they can see and edit what they submitted.
     */
    public function index(Request $request)
    {
        $reviews = Review::where('user_id', $request->user()->id)
            ->with(['product:id,name', 'vendor:id,first_name,last_name,business_name'])
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => true,
            'message' => 'Your reviews fetched successfully.',
            'data' => $reviews,
        ]);
    }

    /**
     * Products from delivered orders that this user has not reviewed yet —
     * the app's "Rate your purchase" prompt reads this.
     */
    public function pending(Request $request)
    {
        $userId = $request->user()->id;

        $reviewedProductIds = Review::where('user_id', $userId)
            ->whereNotNull('product_id')
            ->pluck('product_id');

        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.order_status', ['delivered', 'completed'])
            ->where('orders.user_id', $userId)
            ->whereNotIn('order_items.product_id', $reviewedProductIds)
            ->with(['product:id,name,price'])
            ->select('order_items.*', 'orders.vendor_id', 'orders.order_number')
            ->orderByDesc('order_items.id')
            ->get()
            ->unique('product_id')
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Products awaiting your review fetched.',
            'data' => $items,
        ]);
    }

    /**
     * Leave a review for a purchased product.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = $request->user()->id;

        if (Review::where('user_id', $userId)->where('product_id', $request->product_id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'You have already reviewed this product. Edit your existing review instead.',
            ], 409);
        }

        // A review is "verified" when the reviewer actually received the item.
        // That flag is what the product page badges, and it also decides
        // whether the review skips the moderation queue.
        $deliveredOrder = Order::query()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->where('order_items.product_id', $request->product_id)
            ->whereIn('orders.order_status', ['delivered', 'completed'])
            ->select('orders.vendor_id')
            ->first();

        // A completed service on this product counts the same: the customer
        // had the work done, so the review is verified.
        $deliveredOrder ??= ServiceRequest::query()
            ->join('services', 'services.id', '=', 'service_requests.service_id')
            ->where('service_requests.customer_id', $userId)
            ->where('services.product_id', $request->product_id)
            ->where('service_requests.status', 'completed')
            ->select('service_requests.vendor_id')
            ->first();

        $product = Product::find($request->product_id);

        $review = Review::create([
            'user_id' => $userId,
            'product_id' => $request->product_id,
            'vendor_id' => $deliveredOrder->vendor_id ?? $product->user_id,
            'rating' => $request->rating,
            'title' => $request->title,
            'review_text' => $request->review_text,
            'review_type' => 'product',
            'verified' => (bool) $deliveredOrder,
            'status' => $deliveredOrder ? 'approved' : 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => $deliveredOrder
                ? 'Thank you for your review.'
                : 'Thank you. Your review will appear once it has been reviewed by our team.',
            'data' => $review->load('user:id,first_name,last_name,profile_image'),
        ], 201);
    }

    /**
     * Edit your own review. An edit re-enters moderation unless the purchase
     * was verified, so an approved review cannot be swapped for spam later.
     */
    public function update(Request $request, $id)
    {
        $review = Review::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $review) {
            return response()->json([
                'status' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update([
            'rating' => $request->rating,
            'title' => $request->title,
            'review_text' => $request->review_text,
            'status' => $review->verified ? 'approved' : 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Review updated successfully.',
            'data' => $review,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $review = Review::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $review) {
            return response()->json([
                'status' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'status' => true,
            'message' => 'Review deleted successfully.',
        ]);
    }

    /**
     * Average, total and per-star counts for a product or vendor.
     */
    private function summaryFor(string $column, $value): array
    {
        $rows = Review::approved()
            ->where($column, $value)
            ->select('rating', DB::raw('COUNT(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $breakdown = [];
        $sum = 0;
        $count = 0;

        for ($star = 5; $star >= 1; $star--) {
            $n = (int) ($rows[$star] ?? 0);
            $breakdown[$star] = $n;
            $sum += $star * $n;
            $count += $n;
        }

        return [
            'average_rating' => $count ? round($sum / $count, 2) : 0,
            'total_reviews' => $count,
            'breakdown' => $breakdown,
        ];
    }
}
