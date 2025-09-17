<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\DomainLicense;
use App\Models\Package;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;
    public function __construct(TokenManage $tokenManage){
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = new NativeCoin();
    }
    public function index(){
        $data = Package::all();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validate = request()->validate([
            'domain' => 'required',
            'package_id' => 'required|exists:packages,id',
        ]);

        $packages = Package::where('id', $validate['package_id'])->first();

        $user = $request->user();

        try {
            $ress = $this->tokenManage->sendAnyChainTokenTransaction(
                $user->wallet_address,
                "0x55d398326f99059fF775485246999027B3197955",
                '',
                $this->tokenManage->decrypt($user->two_factor_secret),
                'https://bsc-dataseed.binance.org/',
                '56',
                $user->wallet_address,
                $this->tokenManage->decrypt($user->two_factor_secret),
                $packages->price
            );

            $response = json_decode($ress, true);

            if ($response['status']) {
                DomainLicense::create([
                    'user_id' => $user->id,
                    'package_id' => $validate['package_id'],
                    'domain' => $validate['domain'],
                ]);
                return response()->json([
                    'status' => true,
                    'message' => 'Package added successfully'
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => $response['message']
            ]);
        }catch (\Exception $exception){
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage()
            ]);
        }
    }
}
