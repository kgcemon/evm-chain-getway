<?php

namespace App\Http\Controllers\Single_wallet_transaction;

use App\Http\Controllers\Controller;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;

class Withdrawal extends Controller
{
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;
    public function __construct(TokenManage $tokenManage, NativeCoin $nativeCoin){
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = $nativeCoin;
    }
    public function payout(Request $request)
    {
        $validatedData = $request->validate([
            'amount' => 'required',
            'type' => 'required',
            'to' => 'required',
            'from' => 'required',
            'token_address' => 'required',
            'chain_id' => 'required',
            'rpc_url' => 'required',
        ]);

        if($validatedData['type'] == 'token'){
          return  $this->tokenManage->sendAnyChainTokenTransaction(
                $validatedData['from'],
                $validatedData['token_address'],
                $validatedData['to'],
                $request->header('key'),
                $validatedData['rpc_url'],
                $validatedData['chain_id'],
                $validatedData["amount"],
            );
        }

    }
}
