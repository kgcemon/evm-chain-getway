<?php

namespace App\Http\Controllers\api\Client;
use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\TokenList;
use App\Models\Transactions;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;


class ClientWithdrawController extends Controller
{
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;
    public function __construct(TokenManage $tokenManage){
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = new NativeCoin();
    }

    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.00000001',
            'chain_id' => 'required',
            'token_id' => 'sometimes',
            'address' => 'required|string',
        ]);

        $user = $request->user();
        $chain = ChainList::where('id', $validated['chain_id'])->first();

        if (!$chain) {
            return response()->json([
                'status' => false,
                'message' => 'Chain not found',
            ]);
        }

        $token = null;
        $type = 'native';

        if (!empty($validated['token_id'])) {
            $token = TokenList::where('id', $validated['token_id'])
                ->where('chain_id', $chain->id)
                ->first();

            if ($token) {
                $type = 'token';
            }
        }

        try {
            if ($type === 'token') {
                $ress = $this->tokenManage->sendAnyChainTokenTransaction(
                    $user->wallet_address,
                    $token->contract_address,
                    $validated['address'],
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    $user->wallet_address,
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $validated['amount']
                );
            } else {
                $ress = $this->nativeCoin->sendAnyChainNativeBalance(
                    $user->wallet_address,
                    $validated['address'],
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    false,
                    $validated['amount']
                );
            }

            Transactions::create([
                'user_id'    => $user->id,
                'chain_id'   => $chain->id,
                'amount'     => $ress['amount'],
                'trx_hash'   => $ress['txHash'],
                'type'       => $type,
                'token_name' => $type == 'native' ? $chain->chain_name : $token->token_name,
                'status'     => $ress['status'],
            ]);

            return $ress;
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Withdrawal failed: ' . $e->getMessage(),
            ]);
        }
    }

}
