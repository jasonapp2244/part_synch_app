<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Product categories belong to a company (e.g. "Cummins" > "Fuel Filters").
 * Vendors pick one when adding a product through
 * GET /api/vendor/product-categories/{companyId}, which lists the active rows.
 *
 * The admin only chooses the company; the category and sub-category are
 * copied from it so the three never disagree.
 */
class ProductCategoryController extends Controller
{
    public function index()
    {
        $productCategories = ProductCategory::with('company')->latest('id')->get();
        $companies = Company::orderBy('company_name')->get();

        // ProductCategory has no category relations; look the names up once.
        $categoryNames = Category::whereIn('id', $productCategories->pluck('category_id')->unique())->pluck('name', 'id');
        $subCategoryNames = SubCategory::whereIn('id', $productCategories->pluck('sub_category_id')->unique())->pluck('sub_category_name', 'id');

        return view('admin.view_product_category_records', compact('productCategories', 'companies', 'categoryNames', 'subCategoryNames'));
    }

    public function store(Request $request)
    {
        $this->validated($request);

        $company = Company::findOrFail($request->input('company_id'));

        // `company_product_categories.user_id` is a required foreign key.
        $data = $request->only('product_categories_name', 'description', 'status') + [
            'company_id' => $company->id,
            'category_id' => $company->category_id,
            'sub_category_id' => $company->sub_category_id,
            'user_id' => $request->user()->id,
        ];

        if ($request->hasFile('product_categories_image')) {
            $data['product_categories_image'] = $request->file('product_categories_image')->store('product_categories', 'public');
        }

        ProductCategory::create($data);

        return redirect()->route('product.category.records')->with('success', 'Product category created successfully.');
    }

    public function update(Request $request, $id)
    {
        $productCategory = ProductCategory::findOrFail($id);

        $this->validated($request, $productCategory->id);

        $company = Company::findOrFail($request->input('company_id'));

        $data = $request->only('product_categories_name', 'description', 'status') + [
            'company_id' => $company->id,
            'category_id' => $company->category_id,
            'sub_category_id' => $company->sub_category_id,
        ];

        if ($request->hasFile('product_categories_image')) {
            $old = $productCategory->product_categories_image;
            $data['product_categories_image'] = $request->file('product_categories_image')->store('product_categories', 'public');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $productCategory->update($data);

        return redirect()->route('product.category.records')->with('success', 'Product category updated successfully.');
    }

    public function toggleStatus($id)
    {
        $productCategory = ProductCategory::findOrFail($id);
        $productCategory->status = $productCategory->status === 'active' ? 'inactive' : 'active';
        $productCategory->save();

        return redirect()->route('product.category.records')->with('success', 'Product category status updated.');
    }

    public function destroy($id)
    {
        $productCategory = ProductCategory::findOrFail($id);

        // Both foreign keys below are ON DELETE CASCADE: deleting a category in
        // use would silently delete the products (or vendor accounts) on it.
        $productCount = Product::where('company_product_categories_id', $productCategory->id)->count();
        if ($productCount > 0) {
            return redirect()->route('product.category.records')->with(
                'error',
                "\"{$productCategory->product_categories_name}\" cannot be deleted: {$productCount} product(s) use it. Move or delete those products first, or set the category to inactive."
            );
        }

        if (User::where('company_product_categories_id', $productCategory->id)->exists()) {
            return redirect()->route('product.category.records')->with(
                'error',
                "\"{$productCategory->product_categories_name}\" cannot be deleted: a vendor account is linked to it. Set the category to inactive instead."
            );
        }

        $image = $productCategory->product_categories_image;

        $productCategory->delete();

        if ($image) {
            Storage::disk('public')->delete($image);
        }

        return redirect()->route('product.category.records')->with('success', 'Product category deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'product_categories_name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('company_product_categories', 'product_categories_name')
                    ->where('company_id', $request->input('company_id'))
                    ->ignore($ignoreId),
            ],
            'description' => 'nullable|string|max:500',
            'product_categories_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive',
        ], [
            'product_categories_name.unique' => 'This company already has a product category with that name.',
        ], [
            'company_id' => 'company',
            'product_categories_name' => 'name',
            'product_categories_image' => 'image',
        ]);
    }
}
