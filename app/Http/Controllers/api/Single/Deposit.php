<?php

namespace App\Http\Controllers\api\Single;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\Transactions;
use App\Models\User;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;

class Deposit extends Controller
{
    protected NativeCoin $nativeCoin;
    protected TokenManage $tokenManage;
    public function __construct(NativeCoin $nativeCoin, TokenManage $tokenManage){
        $this->nativeCoin = $nativeCoin;
        $this->tokenManage = $tokenManage;
    }
    public function deposit(Request $request)
    {
        $apiKey = $request->header('Bearer-Token');

        if (!$apiKey) {
            return response()->json(['message'=>"Unauthenticated."], 401);
        }

        $validatedData = $request->validate([
            'to' => 'required|min:20|max:90',
            'token_address' => 'sometimes|string',
            'type' => 'required',
            'user_id' => 'required',
            'chain_id' => 'required|integer',
        ]);


        $user = User::find($validatedData['user_id']);
        $chainData = ChainList::where('chain_id', $validatedData['chain_id'])->first();

        if (!$chainData){
            return response()->json([
                'status'    => false,
                'message' => 'Block master.info not supported this chain please contact your server administrator.',
            ]);
        }

        if (!$user) {
            return response()->json(['message'=>"Unauthenticated."], 401);
        }

        $adminWallet = $user->wallet_address;
        $decryptedKey = $this->tokenManage->decrypt($apiKey);
        $adminKey = $this->tokenManage->decrypt($user->two_factor_secret);
        $tokenContractAddress = $validatedData['token_address'] ?? null;
        $to = $validatedData['to'];
        $rpcUrl = $chainData->chain_rpc_url;
        $chainId = $chainData->chain_id;

        if ($validatedData['type'] === 'native') {
            $res = $this->nativeCoin->sendAnyChainNativeBalance(
                "$to",
                "$adminWallet",
                "$decryptedKey",
                $rpcUrl,
                $chainId,
                true
            );
            if (!empty($res['success']) && !empty($res['tx_hash'])) {
                return response()->json([
                    'status'     => true,
                    'amount' => $res['sent_amount'],
                    'tx_hash'    => $res["tx_hash"],
                ]);
            }
        }elseif ($validatedData['type'] == 'token') {
            $data = $this->tokenManage->sendAnyChainTokenTransaction(
                "$to",
                "$tokenContractAddress",
                $adminWallet,
                "$decryptedKey",
                $rpcUrl,
                $chainId,
                "$adminWallet",
                "$adminKey",
                null,
                true
            );
            $mainData = $data->getData();
            if ($mainData->status === true) {
                try{
                    Transactions::create([
                        'user_id' => $user->id,
                        'chain_id' => $chainData->id,
                        'amount' => $mainData->amount,
                        'trx_hash' => $mainData->txHash,
                        'type' => $validatedData['type'],
                        'token_name' => $tokenContractAddress,
                        'status' => $mainData->status,
                    ]);
                }catch (\Exception $exception){}
                return  $mainData;
            }else{
                return response()->json([
                    'status' => false,
                    'message' => 'no deposit available',
                ]);
            }
        }
    }
}
