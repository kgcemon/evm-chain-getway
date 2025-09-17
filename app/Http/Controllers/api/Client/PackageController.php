<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\Package;

class PackageController extends Controller
{
    public function index(){
        $data = Package::all();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}
