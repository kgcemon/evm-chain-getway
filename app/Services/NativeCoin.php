<?php

namespace App\Services;

use GuzzleHttp\Client;
use Web3p\EthereumTx\Transaction;

class NativeCoin extends Crypto
{
    public function sendAnyChainNativeBalance(
        $fromAddress,
        $toAddress,
        $encryptedPrivateKey,
        $rpcUrl,
        $chainId,
        $isFullOut = false,
        $amount = null
    ): array {
        try {
            if (!extension_loaded('gmp')) {
                return $this->apiResponse(false, 'GMP extension is required.');
            }

            $client = new Client();

            // Get nonce
            $nonce = hexdec(json_decode($client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getTransactionCount',
                    'params' => [$fromAddress, 'pending'],
                    'id' => 1
                ]
            ])->getBody(), true)['result']);

            // Get minimum possible gas price using eth_feeHistory
            $feeHistory = json_decode($client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_feeHistory',
                    'params' => ['0x1', 'latest', []],
                    'id' => 2
                ]
            ])->getBody(), true);

            $baseFee = isset($feeHistory['result']['baseFeePerGas'])
                ? hexdec(end($feeHistory['result']['baseFeePerGas']))
                : hexdec('0x3B9ACA00'); // fallback 1 Gwei if not available

            // Add very low priority fee (1 Gwei)
            $priorityFee = gmp_init(bcmul('1', bcpow('10', '9', 0)));
            $gasPrice = gmp_add($baseFee, $priorityFee);

            // Fixed gas limit for native transfer
            $gasLimit = 21000;
            $gasCost = gmp_mul($gasPrice, gmp_init($gasLimit));

            // Get balance
            $balanceHex = json_decode($client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBalance',
                    'params' => [$fromAddress, 'latest'],
                    'id' => 3
                ]
            ])->getBody(), true)['result'];

            $balanceWei = gmp_init($balanceHex, 16);
            if (gmp_cmp($balanceWei, 0) <= 0) {
                return $this->apiResponse(false, 'Insufficient balance.');
            }

            // Amount calculation
            if ($isFullOut && $amount == null) {
                $amountWei = gmp_sub($balanceWei, $gasCost);
                if (gmp_cmp($amountWei, 0) <= 0) {
                    return $this->apiResponse(false, 'Not enough to cover gas.');
                }
            } elseif ($amount !== null) {
                $amountWei = gmp_init(bcmul($amount, bcpow('10', '18', 0)), 10);
                $totalCost = gmp_add($amountWei, $gasCost);
                if (gmp_cmp($balanceWei, $totalCost) < 0) {
                    return $this->apiResponse(false, 'Not enough balance to send given amount and gas.');
                }
            } else {
                return $this->apiResponse(false, 'Amount is required when not using full out.');
            }

            $amountInEther = bcdiv(gmp_strval($amountWei), bcpow('10', '18'), 18);

            // Create and sign transaction
            $tx = new Transaction([
                'nonce' => '0x' . dechex($nonce),
                'gasPrice' => '0x' . gmp_strval($gasPrice, 16),
                'gas' => '0x' . dechex($gasLimit),
                'to' => $toAddress,
                'value' => '0x' . gmp_strval($amountWei, 16),
                'chainId' => $chainId
            ]);

            $signedTx = '0x' . $tx->sign($encryptedPrivateKey);

            // Broadcast transaction
            $response = json_decode($client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => [$signedTx],
                    'id' => 4
                ]
            ])->getBody(), true);

            if (isset($response['result'])) {
                return $this->apiResponse(true, 'Transaction sent successfully.', [
                    'txHash' => $response['result'],
                    'amount' => $amountInEther
                ]);
            }

            return $this->apiResponse(false, $response['error']['message'] ?? 'Unknown error occurred while sending transaction.');
        } catch (\Exception $e) {
            return $this->apiResponse(false, 'Exception: ' . $e->getMessage());
        }
    }

    private function apiResponse(bool $status, string $message, $data = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'txHash' => $data['txHash'] ?? null,
            'amount' => $data['amount'] ?? null,
        ];
    }
}
