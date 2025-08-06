<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Services\CheckBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ClientWalletBalanceController extends Controller
{
    protected CheckBalance $checkBalance;
    public function __construct(CheckBalance $checkBalance){
        $this->checkBalance = $checkBalance;
    }


    public function balanceList(Request $request)
    {
        $user = $request->user();
        $wallet = $user->wallet_address;

        $cacheKey = 'balance_list_' . $user->id;

        $cachedData = Cache::get($cacheKey);
//        if ($cachedData) {
//            return response()->json([
//                'success' => true,
//                'data' => $cachedData,
//                'cached' => true
//            ]);
//        }

//        $allChain = ChainList::with('token')->get();
//        $list = [];

        try {
            foreach ($allChain as $chain) {
                $nativeBalance = (float) $this->checkBalance->balance($chain->chain_rpc_url, $wallet);

                $tokenBalances = [];
                foreach ($chain->token as $token) {
                    $balance = (float) $this->checkBalance->balance(
                        $chain->chain_rpc_url,
                        $wallet,
                        'token',
                        $token->contract_address
                    );

                    if ($balance > 0) {
                        $tokenBalances[] = [
                            'id' => $token->id,
                            'chain_id' => $chain->id,
                            'name' => $token->token_name ?? '',
                            'symbol' => $token->symbol ?? '',
                            'balance' => number_format($balance, 4, '.', ''), // 4 decimal
                            'icon' => $token->icon ?? null,
                        ];
                    }
                }

                if ($nativeBalance > 0 || count($tokenBalances) > 0) {
                    $list[] = [
                        'id' => $chain->id,
                        'chain' => $chain->chain_name,
                        'icon' => $chain->icon ?? null,
                        'native_balance' => number_format($nativeBalance, 4, '.', ''),
                        'tokens' => $tokenBalances,
                    ];
                }
            }

            Cache::put($cacheKey, $list, now()->minute(1000));

            return response()->json([
                'success' => true,
                'data' => $list,
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }


}
