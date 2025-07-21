<?php

namespace App\Services;
use Web3p\EthereumTx\Transaction;

class TokenManage extends Crypto
{

    public function sendAnyChainTokenTransaction($senderAddress, $tokenAddress, $toAddress, $userKey, $rpcUrl, $chainId, $adminAddress, $adminKey, $amount = null, $isFullOut = false)
    {
        // Validate addresses
        if (!$this->isValidAddress($senderAddress) || !$this->isValidAddress($tokenAddress) || !$this->isValidAddress($toAddress)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid address'
            ]);
        }

        // Get token balance
        $balanceInWei = (string) $this->getTokenBalance($rpcUrl, $senderAddress, $tokenAddress);

        $balanceInWei = $this->toPlainString($balanceInWei);

        if (!is_numeric($balanceInWei) || $balanceInWei === '0') {
            return response()->json([
                'status' => false,
                'message' => 'Token balance is invalid or zero'
            ]);
        }

        // Determine amount to send
        $decimals = 18;
        if ($isFullOut && $amount === null) {
            $amountInWei = $balanceInWei;
        } else {
            if (!is_numeric($amount)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Amount must be a valid number'
                ]);
            }

            $amountInWei = bcmul((string)$amount, bcpow('10', (string)$decimals), 0);
            $amountInWei = $this->toPlainString($amountInWei);

            if (!is_numeric($amountInWei) || bccomp($balanceInWei, $amountInWei) < 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient token balance to send the specified amount'
                ]);
            }
        }

        // Prepare data for token transfer
        $data = '0xa9059cbb' .
            str_pad(substr($this->removeHexPrefix($toAddress), 0, 64), 64, '0', STR_PAD_LEFT) .
            str_pad($this->bcdechex($amountInWei), 64, '0', STR_PAD_LEFT);

        $gasLimit = 68000;
        $gasPrice = 1000000000;
        $gasFeeInWei = bcmul((string)$gasLimit, (string)$gasPrice);

        // Send gas to sender
        $this->sendGasFee("$rpcUrl", "$senderAddress", "$gasFeeInWei", "$adminKey", "$chainId", $adminAddress);
        sleep(1);
        // Prepare transaction
        $nonce = $this->getNonce($rpcUrl, $senderAddress);
        $transaction = [
            'nonce' => '0x' . dechex($nonce),
            'from' => $senderAddress,
            'to' => $tokenAddress,
            'value' => '0x0',
            'gas' => '0x' . dechex($gasLimit),
            'gasPrice' => '0x' . dechex($gasPrice),
            'data' => $data,
            'chainId' => $chainId
        ];

        // Sign and send
        $tx = new Transaction($transaction);
        $signedTx = $tx->sign($userKey);
        $txHash = $this->sendRawTransaction($rpcUrl, $signedTx);

        return response()->json([
            'status' => true,
            'nonce' => $nonce,
            'txHash' => $txHash,
           'amount' => bcdiv($amountInWei, bcpow('10', '18'), 18)
        ]);
    }
    private function sendGasFee($rpcUrl, $toAddress, $estimatedGasFee, $adminKey, $chainId, $adminAddress)
    {
        if (!$this->isValidAddress($toAddress)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid address'
            ]);
        }

        $nonce = $this->getNonce($rpcUrl, $adminAddress);

        $gasLimit = 60000;
        $gasPrice = 5000000000;

        $currentBalance = $this->toPlainString($this->getNativeBalance($rpcUrl, $toAddress));
        $totalNeeded = $this->toPlainString(bcadd($estimatedGasFee, '0'));

        if (bccomp($currentBalance, $totalNeeded) >= 0) {
            error_log("User already has enough gas, skipping top-up.");
            return null;
        }

        $requiredTopUp = $this->toPlainString(bcsub($totalNeeded, $currentBalance));

        $transaction = [
            'nonce' => '0x' . dechex($nonce),
            'from' => $adminAddress,
            'to' => $toAddress,
            'value' => '0x' . dechex($requiredTopUp),
            'gas' => '0x' . dechex($gasLimit),
            'gasPrice' => '0x' . dechex($gasPrice),
            'chainId' => $chainId
        ];

        $tx = new Transaction($transaction);
        $signedTx = $tx->sign($adminKey);
        $txHash = $this->sendRawTransaction($rpcUrl, $signedTx);

        $this->waitForTransaction($rpcUrl, $txHash);

        return $txHash;
    }

    private function getNativeBalance($rpcUrl, $address): float|int
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_getBalance',
            'params' => [$address, 'latest'],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        return hexdec($this->removeHexPrefix($response['result']));
    }

    private function getTokenBalance($rpcUrl, $address, $tokenAddress)
    {
        $data = '0x70a08231' . str_pad(substr($this->removeHexPrefix($address), 0, 64), 64, '0', STR_PAD_LEFT);
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_call',
            'params' => [
                [
                    'to' => $tokenAddress,
                    'data' => $data
                ],
                'latest'
            ],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);

        return hexdec($this->removeHexPrefix($response['result']));
    }

    private function toPlainString($number)
    {
        if (!is_numeric($number)) return '0';

        if (stripos($number, 'e') !== false) {
            return number_format($number, 0, '', '');
        }

        return (string) $number;
    }


    private function getNonce($rpcUrl, $address)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionCount',
            'params' => [$address, 'pending'],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        return hexdec($response['result']);
    }

    private function estimateGas($rpcUrl, $from, $to, $data)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_estimateGas',
            'params' => [[
                'from' => $from,
                'to' => $to,
                'data' => $data
            ]],
            'id' => 1
        ];
        try {
            $response = $this->sendRpcRequest($rpcUrl, $postData);
            return hexdec($this->removeHexPrefix($response['result']));
        } catch (\Exception $e) {
            error_log("Failed to estimate gas for transaction from {$from} to {$to}: " . $e->getMessage());
            throw new \Exception("Gas estimation failed: " . $e->getMessage());
        }
    }

    private function getGasPrice($rpcUrl)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_gasPrice',
            'params' => [],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        return hexdec($response['result']);
    }

    private function sendRawTransaction($rpcUrl, $signedTx)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_sendRawTransaction',
            'params' => ['0x' . $signedTx],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        $txHash = $response['result'];

        // Validate transaction hash format
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            error_log("Invalid transaction hash returned: " . $txHash);
            throw new \Exception("Invalid transaction hash returned: " . $txHash);
        }

        return $txHash;
    }

    private function waitForTransaction($rpcUrl, $txHash)
    {
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            error_log("Invalid transaction hash provided to waitForTransaction: " . $txHash);
            throw new \Exception("Invalid transaction hash: " . $txHash);
        }

        $maxAttempts = 60;
        $attempt = 0;
        $delay = 3;

        while ($attempt < $maxAttempts) {
            $postData = [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1
            ];

            try {
                $response = $this->sendRpcRequest($rpcUrl, $postData);

                // Proceed only if result is set
                if (array_key_exists('result', $response)) {
                    if ($response['result'] !== null) {
                        $status = $response['result']['status'] ?? null;
                        if ($status === '0x1') {
                            error_log("Transaction confirmed successfully: $txHash");
                            return true;
                        } elseif ($status === '0x0') {
                            error_log("Transaction failed with status 0x0: " . json_encode($response['result']));
                            throw new \Exception("Transaction failed: $txHash");
                        }
                    }
                    // If result is null, transaction not yet mined
                    error_log("Transaction $txHash pending (null result), attempt " . ($attempt + 1) . " of $maxAttempts");
                } else {
                    // No result key present
                    error_log("Transaction $txHash RPC response missing 'result', attempt " . ($attempt + 1) . " of $maxAttempts: " . json_encode($response));
                }

            } catch (\Exception $e) {
                error_log("Error checking transaction receipt for $txHash: " . $e->getMessage());
                // Don't throw immediately, wait and retry
            }

            sleep($delay);
            $attempt++;
        }

        error_log("Transaction $txHash timed out after $maxAttempts attempts");
        throw new \Exception("Transaction timed out: $txHash");
    }


    private function sendRpcRequest($rpcUrl, $postData)
    {
        $ch = curl_init($rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("RPC request failed for method {$postData['method']}: $curlError");
            throw new \Exception("RPC request failed: $curlError");
        }

        if ($httpCode >= 400) {
            error_log("RPC request returned HTTP error {$httpCode} for method {$postData['method']}: $response");
            throw new \Exception("RPC request returned HTTP error: $httpCode");
        }

        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            error_log("Invalid JSON response for method {$postData['method']}: $response");
            throw new \Exception("Invalid JSON response from RPC");
        }

        error_log("RPC response for method {$postData['method']}: " . json_encode($decodedResponse));

        if (isset($decodedResponse['error'])) {
            error_log("RPC error for method {$postData['method']}: " . json_encode($decodedResponse['error']));
            throw new \Exception("RPC error: " . $decodedResponse['error']['message'] . " (code: " . $decodedResponse['error']['code'] . ")");
        }

        if (!isset($decodedResponse['result'])) {
            error_log("RPC response missing 'result' for method {$postData['method']}: " . json_encode($decodedResponse));
            throw new \Exception("RPC response does not contain 'result' key for method {$postData['method']}");
        }

        return $decodedResponse;
    }

    private function bcdechex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last) . $hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);
        return $hex;
    }

    private function removeHexPrefix($hex)
    {
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }

    private function isValidAddress($address)
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
}
