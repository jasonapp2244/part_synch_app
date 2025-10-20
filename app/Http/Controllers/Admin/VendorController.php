<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function VendorRecords(){
        return view('admin.view_vendor_records');
    }
}
