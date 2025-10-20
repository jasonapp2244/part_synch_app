<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function CategoryRecords()
    {
        return view('admin.view_category_records');
    }
}
