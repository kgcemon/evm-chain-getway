<?php

namespace App\Http\Controllers\api\Single;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayOutRequest;
use App\Models\Transactions;
use App\Models\User;
use App\Services\NativeCoin;
use App\Services\TokenManage;

class Withdrawal extends Controller
{
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;

    public function __construct(TokenManage $tokenManage, NativeCoin $nativeCoin)
    {
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = $nativeCoin;
    }

    public function payout(PayOutRequest $validatedData)
    {
        $user = User::find($validatedData['user_id']);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found or API access denied.'
            ]);
        }

        $userWallet = $user->wallet_address;
        $decryptedKey = $this->tokenManage->decrypt($user->two_factor_secret);
        $tokenContractAddress = $validatedData['token_address'];
        $to = $validatedData['to'];
        $rpcUrl = $validatedData['rpc_url'];
        $chainId = $validatedData['chain_id'];
        $amount = $validatedData['amount'];

        switch ($validatedData['type']) {
            case 'token':
                $res = $this->tokenManage->sendAnyChainTokenTransaction(
                    $userWallet,
                    $tokenContractAddress,
                    $to,
                    $decryptedKey,
                    $rpcUrl,
                    $chainId,
                    $userWallet,
                    $decryptedKey,
                    $amount
                );

                try {
                    Transactions::create([
                        'user_id' => $user->id,
                        'chain_id' => $chainId,
                        'amount' => $res['amount'],
                        'trx_hash' => $res['txHash'],
                        'type' => $validatedData['type'],
                        'token_name' => $tokenContractAddress,
                        'status' => $res['status'],
                    ]);
                }catch (\Exception $exception){
                }
                return $res;

            case 'native':
                try {
                    $res = $this->nativeCoin->
                    sendAnyChainNativeBalance(
                        "$userWallet",
                         $to,
                        $decryptedKey,
                        $rpcUrl,
                        $chainId,
                        false,
                        $amount
                    );
                    try {
                        Transactions::create([
                            'user_id' => $user->id,
                            'chain_id' => $chainId,
                            'amount' => $res['amount'],
                            'trx_hash' => $res['txHash'],
                            'type' => $validatedData['type'],
                            'token_name' => $tokenContractAddress,
                            'status' => $res['status'],
                        ]);
                    }catch (\Exception $exception){
                        return response()->json([
                            'status' => false,
                            'message' => $exception->getMessage()
                        ]);
                    }
                    return $res;
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
