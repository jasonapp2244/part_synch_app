<?php

namespace App\Http\Controllers\Admin;

use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\VendorBoost;
use App\Services\BoostCapacity;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class BoostPackageController extends Controller
{
    // API endpoint (existing)
    public function index()
    {
        $packages = BoostPackage::where('status', 1)->get();
        return response()->json($packages);
    }

    // Admin web view
    public function list()
    {
        $packages = BoostPackage::all();
        $positions = BoostPosition::orderBy('priority')->get();
        $capacity = app(BoostCapacity::class);
        $slotsInUse = $positions->mapWithKeys(fn ($p) => [$p->id => $capacity->inUseNow($p)])->all();

        return view('admin.view_boost_packages', compact('packages', 'positions', 'slotsInUse'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'product_limit' => 'required|integer|min:1',
            'duration_days' => 'required|integer|min:1',
            'currency' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        BoostPackage::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'price' => $request->price,
            'product_limit' => $request->product_limit,
            'duration_days' => $request->duration_days,
            'currency' => $request->currency ?? 'usd',
            'status' => true,
            'description' => $request->description,
        ]);

        return redirect()->route('boost.packages')->with('success', 'Boost package created successfully.');
    }

    public function update(Request $request, $id)
    {
        $package = BoostPackage::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'product_limit' => 'required|integer|min:1',
            'duration_days' => 'required|integer|min:1',
            'currency' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $package->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'price' => $request->price,
            'product_limit' => $request->product_limit,
            'duration_days' => $request->duration_days,
            'currency' => $request->currency ?? 'usd',
            'description' => $request->description,
        ]);

        return redirect()->route('boost.packages')->with('success', 'Boost package updated successfully.');
    }

    public function toggleStatus($id)
    {
        $package = BoostPackage::findOrFail($id);
        $package->status = !$package->status;
        $package->save();

        return redirect()->route('boost.packages')->with('success', 'Package status updated.');
    }

    public function destroy($id)
    {
        $package = BoostPackage::findOrFail($id);

        // vendor_boosts.package_id cascades on delete, so deleting a package
        // that was ever bought would wipe paid boosts and their history.
        if (VendorBoost::where('package_id', $package->id)->exists()) {
            return redirect()->route('boost.packages')
                ->withErrors(['package' => "{$package->name} has boosts on record, so it cannot be deleted. Set it to Inactive to stop new requests."]);
        }

        $package->delete();

        return redirect()->route('boost.packages')->with('success', 'Boost package deleted.');
    }

    /**
     * Capacity and pricing of a home-page position. display_limit is how many
     * products it shows at once, which is also how many boost slots it has.
     */
    public function updatePosition(Request $request, $id)
    {
        $position = BoostPosition::findOrFail($id);

        $data = $request->validate([
            'display_limit' => 'required|integer|min:1|max:50',
            'price_multiplier' => 'required|numeric|min:0.1|max:9.99',
            'status' => 'nullable|boolean',
        ]);

        $position->update([
            'display_limit' => $data['display_limit'],
            'price_multiplier' => $data['price_multiplier'],
            'status' => $request->boolean('status'),
        ]);

        return redirect()->route('boost.packages')->with('success', "{$position->name} updated.");
    }
}
