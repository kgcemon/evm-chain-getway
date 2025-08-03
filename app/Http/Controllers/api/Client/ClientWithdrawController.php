<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\api\Single\Withdrawal;
use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\TokenList;
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
        $validate = $request->validate([
            'amount' => 'required',
            'chain_id' => 'required',
            'token_id' => 'sometimes',
            'address' => 'required',
        ]);

        $user = $request->user();

        $chain = ChainList::where('chain_id',$validate['chain_id'])->first();
        $token = null;
        if (!$chain) {
            return response()->json([
                'status' => false,
                'message' => 'Chain not found',
            ]);
        }

        $tokenID =$validate['token_id'];

        if($tokenID){
            $token = TokenList::where('id',$validate['token_id'])->where('chain_id',$validate['chain_id'])->first();
        }

        $type = $token != null ? 'token' : 'native';

        switch ($type) {
            case 'token':
                return $this->tokenManage->sendAnyChainTokenTransaction(
                    $user->wallet_address,
                    $token->contract_address,
                    $validate['address'],
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    $user->wallet_address,
                    $this->tokenManage->decrypt($user->two_factor_secret),
                    $validate['amount']
                );

            case 'native':
                try {
                    return $this->nativeCoin->
                    sendAnyChainNativeBalance(
                        "$user->wallet_address",
                        $validate['address'],
                        $this->tokenManage->decrypt($user->two_factor_secret),
                        $chain->chain_rpc_url,
                        $chain->chain_id,
                        false,
                        $validate['amount']
                    );

                }catch (\Exception $exception){
                    return response()->json([
                        'status' => false,
                        'message' => $exception->getMessage()
                    ]);
                }

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid withdrawal type.'
                ]);
        }
    }
}
