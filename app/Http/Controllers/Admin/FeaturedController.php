<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FeaturedController extends Controller
{
    public function FeaturedRecords()
    {
        return view('admin.view_featured_records');
    }
}
