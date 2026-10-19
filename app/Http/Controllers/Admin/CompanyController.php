<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function CompanyRecords()
    {
        $companies = Company::with('user')->get();
        $categories = Category::all();
        $subCategories = SubCategory::where('status', 'active')->get();
        return view('admin.view_company_records', compact('companies', 'categories', 'subCategories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'company_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        // `companies.user_id` is a required foreign key; see CategoryController::store.
        $data = $request->only('company_name', 'description', 'category_id', 'sub_category_id', 'status')
            + ['user_id' => $request->user()->id];

        if ($request->hasFile('company_image')) {
            $data['company_image'] = $request->file('company_image')->store('companies', 'public');
        }

        Company::create($data);

        return redirect()->route('company.records')->with('success', 'Company created successfully.');
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $request->validate([
            'company_name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'company_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        $data = $request->only('company_name', 'description', 'category_id', 'sub_category_id', 'status');

        if ($request->hasFile('company_image')) {
            if ($company->company_image) {
                Storage::disk('public')->delete($company->company_image);
            }
            $data['company_image'] = $request->file('company_image')->store('companies', 'public');
        }

        $company->update($data);

        return redirect()->route('company.records')->with('success', 'Company updated successfully.');
    }

    public function toggleStatus($id)
    {
        $company = Company::findOrFail($id);
        $company->status = $company->status === 'active' ? 'inactive' : 'active';
        $company->save();

        return redirect()->route('company.records')->with('success', 'Company status updated.');
    }

    public function destroy($id)
    {
        $company = Company::findOrFail($id);

        if ($company->company_image) {
            Storage::disk('public')->delete($company->company_image);
        }

        $company->delete();

        return redirect()->route('company.records')->with('success', 'Company deleted successfully.');
    }
}
