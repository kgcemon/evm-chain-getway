<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Services\CheckBalance;
use Illuminate\Http\Request;


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

        $allChain = ChainList::with('token')->get();
        $list = [];

        foreach ($allChain as $chain) {
            $nativeBalance = $this->checkBalance->balance($chain->chain_rpc_url, $wallet, 'native');

            $tokenBalances = [];
            foreach ($chain->token as $token) {
                $balance = $this->checkBalance->balance(
                    $chain->chain_rpc_url,
                    $wallet,
                    'token',
                    $token->contract_address // এটা ধরেই নিচ্ছি টেবিলে আছে
                );

                $tokenBalances[] = [
                    'name' => $token->token_name ?? '',
                    'symbol' => $token->symbol ?? '',
                    'balance' => $balance,
                    'icon' => $token->icon ?? null,
                ];
            }

            $list[] = [
                'chain' => $chain->chain_name,
                'icon' => $chain->icon ?? null,
                'native_balance' => $nativeBalance,
                'tokens' => $tokenBalances,
            ];
        }

        return response()->json($list);
    }


}
