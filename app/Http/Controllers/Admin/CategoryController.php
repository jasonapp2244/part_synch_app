<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function CategoryRecords()
    {
        $categories = Category::withCount('subCategories')->get();
        return view('admin.view_category_records', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'category_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // `categories.user_id` is a required foreign key (the migration's
        // `->nullable()` sits after `constrained()` and never reached the
        // column), so leaving it out made every create a 500.
        $data = [
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
        ];

        if ($request->hasFile('category_image')) {
            $path = $request->file('category_image')->store('categories', 'public');
            $data['category_image'] = $path;
        }

        Category::create($data);

        return redirect()->route('category.records')->with('success', 'Category created successfully.');
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'category_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
        ];

        if ($request->hasFile('category_image')) {
            if ($category->category_image) {
                Storage::disk('public')->delete($category->category_image);
            }
            $data['category_image'] = $request->file('category_image')->store('categories', 'public');
        }

        $category->update($data);

        return redirect()->route('category.records')->with('success', 'Category updated successfully.');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        if ($category->category_image) {
            Storage::disk('public')->delete($category->category_image);
        }

        $category->delete();

        return redirect()->route('category.records')->with('success', 'Category deleted successfully.');
    }
}
