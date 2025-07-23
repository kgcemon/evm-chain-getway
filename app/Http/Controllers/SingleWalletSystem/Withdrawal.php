<?php

namespace App\Http\Controllers\SingleWalletSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayOutRequest;
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
                return $this->tokenManage->sendAnyChainTokenTransaction(
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

            case 'native':
                try {
                    return $this->nativeCoin->
                    sendAnyChainNativeBalance(
                        "$userWallet",
                         $to,
                        $decryptedKey,
                        $rpcUrl,
                        $chainId,
                        false,
                        $amount
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
