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
            'chain_id' => 'required|exists:chain_lists,id',
            'token_id' => 'sometimes|nullable|exists:token_lists,id',
            'address' => 'required|string',
        ]);

        $user = $request->user();
        $chain = ChainList::find($validated['chain_id']);

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


            $responseData = is_array($ress) ? $ress : json_decode(json_encode($ress), true);

            Transactions::create([
                'user_id'    => $user->id,
                'chain_id'   => $chain->id,
                'amount'     => (float) ($responseData['amount'] ?? 0),
                'trx_hash'   => $responseData['txHash'] ?? null,
                'type'       => $type,
                'token_name' => $chain->chain_name,
                'status'     => ($responseData['status'] ?? false) ? 1 : 0,
            ]);

            return response()->json($responseData);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Withdrawal failed: ' . $e->getMessage(),
            ]);
        }
    }

}
