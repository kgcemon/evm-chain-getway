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
        $validate = $request->validate([
            'amount' => 'required',
            'chain_id' => 'required',
            'token_id' => 'sometimes',
            'address' => 'required',
        ]);

        $user = $request->user();

        $chain = ChainList::where('id',$validate['chain_id'])->first();
        $token = null;
        if (!$chain) {
            return response()->json([
                'status' => false,
                'message' => 'Chain not found',
            ]);
        }

        $tokenID = $validate['token_id'] ?? null;

        if($tokenID != null){
            $token = TokenList::where('id',$validate['token_id'])->where('chain_id',$validate['chain_id'])->first();
        }

        $type = $token != null ? 'token' : 'native';

        switch ($type) {
            case 'token':
                $ress = $this->tokenManage->sendAnyChainTokenTransaction(
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

                try {
                    Transactions::create([
                        'user_id' => $user->id,
                        'chain_id' => $chain->chain_id,
                        'amount' => $ress->amount,
                        'trx_hash' => $ress->txHash,
                        'type' => $type,
                        'token_name' => $chain->chain_name,
                        'status' => $ress->status,
                    ]);
                }catch (\Exception $exception){}

            return $ress;

            case 'native':
                try {
                    $ress = $this->nativeCoin->
                    sendAnyChainNativeBalance(
                        "$user->wallet_address",
                        $validate['address'],
                        $this->tokenManage->decrypt($user->two_factor_secret),
                        $chain->chain_rpc_url,
                        $chain->chain_id,
                        false,
                        $validate['amount']
                    );

                    $data = json_decode($ress,true);

                         $da =  Transactions::create([
                               'user_id' => $user->id,
                               'chain_id' => $chain->id,
                               'amount' => $data->amount,
                               'trx_hash' => $data->txHash,
                               'type' => $type,
                               'token_name' => $chain->chain_name,
                               'status' => $data->status,
                           ]);

                    return $da;
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
