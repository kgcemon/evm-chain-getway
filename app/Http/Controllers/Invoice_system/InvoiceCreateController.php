<?php

namespace App\Http\Controllers\Invoice_system;

use App\Http\Controllers\Controller;
use App\Models\PaymentJobs;
use App\Services\CreateWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceCreateController extends Controller
{
    protected CreateWallet $createWallet;

    public function __construct(CreateWallet $createWallet)
    {
        $this->createWallet = $createWallet;
    }


    public function createInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'webhook_url'      => 'required|string|url',
            'token_name'       => 'sometimes|string|nullable',
            'chain_id'         => 'required|integer',
            'type'             => 'required|string',
            'contract_address' => 'nullable|string',
            'user_id'          => 'required|integer|exists:users,id',
        ]);

        $rpcUrl = $this->findChainRpc($validated['chain_id']);
        if (is_null($rpcUrl)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid chain ID.',
            ], 400);
        }

        $wallet = $this->createWallet->createAddress();
        if (!$wallet || !isset($wallet['address'], $wallet['key'])) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to generate wallet address.',
            ], 500);
        }

        $job = PaymentJobs::create([
            'wallet_address'   => $wallet['address'],
            'key'              => $wallet['key'],
            'webhook_url'      => $validated['webhook_url'],
            'token_name'       => strtoupper($validated['token_name']) ?? null,
            'chain_id'         => $validated['chain_id'],
            'rpc_url'          => $rpcUrl,
            'type'             => $validated['type'],
            'contract_address' => $validated['contract_address'] ?? null,
            'invoice_id'       => PaymentJobs::generateUIDCode(),
            'user_id'          => $validated['user_id'],
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Invoice has been created.',
            'data'    => [
                'invoice_id' => $job->invoice_id,
                'address'    => $this->createWallet->decrypt($wallet['address']),
            ],
        ], 201);
    }

    private function findChainRpc($chainId):string|null{
       if($chainId == 56){
            return 'https://bsc-dataseed.binance.org/';
        }elseif ($chainId == 9996){
            return 'http://194.163.189.70:8545/';
        }
        return null;
    }
}
