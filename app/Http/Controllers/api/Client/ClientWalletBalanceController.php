<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\User;
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

        // Clear cache if needed
        Cache::forget($cacheKey);

        if ($cachedData = Cache::get($cacheKey)) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cached' => true
            ]);
        }

        $allChain = ChainList::with('token')->get();
        $list = [];

        foreach ($allChain as $chain) {
            $nativeBalance = 0;
            $tokenBalances = [];

            try {
                // Native balance
                $nativeBalance = (float) $this->checkBalance->balance($chain->chain_rpc_url, $wallet) ?? 0;
            } catch (\Exception $exception) {
                // যদি RPC error দেয়, তাহলে ওই চেইন skip হবে
                continue;
            }

            foreach ($chain->token as $token) {
                $balance = 0;
                try {
                    $balance = (float) $this->checkBalance->balance(
                        $chain->chain_rpc_url,
                        $wallet,
                        'token',
                        $token->contract_address
                    );
                } catch (\Exception $exception) {
                    // এক টোকেন error দিলে ওই টোকেন বাদ যাবে, বাকি গুলো চলবে
                    continue;
                }

                if ($balance > 0 || $token->token_name == 'USDT') {
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

            // শুধুমাত্র যেসব chain এ কিছু balance আছে, সেগুলো return করব
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

        Cache::put($cacheKey, $list, now()->addMinutes(30));

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    public function BalanceCheck(Request $request)
    {
        $data = $request->validate([
            'chain_id'         => 'required|integer',
            'user_id'          => 'required|integer|exists:users,id',
            'type'             => 'required|string',
            'contract_address' => 'nullable|string',
            'address'          => 'required|string',
        ]);

        // Check user existence (already validated with `exists`, so this is optional unless you need the model)
        $user = User::find($data['user_id']);
        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Find chain
        $chain = ChainList::where('chain_id', $data['chain_id'])->first();
        if (!$chain) {
            return response()->json([
                'status'  => false,
                'message' => 'Chain not found',
            ], 404);
        }

        $contractAddress = $data['contract_address'] ?? null;

        return $this->checkBalance->balance(
            $chain->chain_rpc_url,
            $data['address'],
            $data['type'],
            $contractAddress
        );
    }



}
