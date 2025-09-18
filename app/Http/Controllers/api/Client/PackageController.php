<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\DomainLicense;
use App\Models\Package;
use App\Models\Transactions;
use App\Services\CheckBalance;
use App\Services\TokenManage;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    protected TokenManage $tokenManage;
    protected CheckBalance $checkBalance;

    public function __construct(TokenManage $tokenManage){
        $this->tokenManage = $tokenManage;
        $this->checkBalance = new CheckBalance();
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
        $rpurl = 'https://bsc-dataseed.binance.org/';
        $contractAddress = '0x55d398326f99059fF775485246999027B3197955';

        $validate = request()->validate([
            'domain' => 'required',
            'package_id' => 'required|exists:packages,id',
        ]);

        $packages = Package::where('id', $validate['package_id'])->first();

        $user = $request->user();

        $balance = $this->checkBalance->balance(
            $rpurl,
            "$user->wallet_address",
            "token",
            "$contractAddress",
        );

//        if ($balance < $packages->price) {
//            return response()->json([
//                'status' => false,
//                'message' => 'Insufficient balance for this package.'
//            ]);
//        }

        try {
            $ress = $this->tokenManage->sendAnyChainTokenTransaction(
                $user->wallet_address,
                $contractAddress,
                '0xDD4A92c37C176F83B0aeb127483009E5b51E65E5',
                $this->tokenManage->decrypt($user->two_factor_secret),
                $rpurl,
                '56',
                $user->wallet_address,
                $this->tokenManage->decrypt($user->two_factor_secret),
                $packages->price
            );



            if ($ress['status']) {
                DomainLicense::create([
                    'user_id' => $user->id,
                    'package_id' => $validate['package_id'],
                    'domain' => $validate['domain'],
                    'register_at' => now(),
                    'expires_at' => now()->addMonth(),
                ]);
                Transactions::create([
                    'user_id'    => $user->id,
                    'chain_id'   => 2,
                    'amount'     => $ress['amount'],
                    'trx_hash'   => $ress['txHash'],
                    'type'       => 'credit',
                    'token_name' => 'USDT',
                    'status'     => $ress['status'],
                ]);
                return response()->json([
                    'status' => true,
                    'message' => 'Package added successfully'
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => $ress['message']
            ]);
        }catch (\Exception $exception){
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage()
            ]);
        }
    }

    public function license(Request $request){
        $user = $request->user();
        $license = DomainLicense::where('user_id', $user->id)->with('package')->get();
        return response()->json([
            'status' => true,
            'data' => $license
        ]);
    }
}
