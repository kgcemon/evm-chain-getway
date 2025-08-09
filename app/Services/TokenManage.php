<?php

namespace App\Services;

use App\Exceptions\RpcException;
use Web3p\EthereumTx\Transaction;

class TokenManage extends Crypto
{
    public function sendAnyChainTokenTransaction($senderAddress, $tokenAddress, $toAddress, $userKey, $rpcUrl, $chainId, $adminAddress, $adminKey, $amount = null, $isFullOut = false)
    {
        if (!$this->isValidAddress($senderAddress) || !$this->isValidAddress($tokenAddress) || !$this->isValidAddress($toAddress)) {
            return $this->apiResponse(false, 'Invalid address');
        }

        // Get token balance
        $balanceInWei = (string) $this->getTokenBalance($rpcUrl, $senderAddress, $tokenAddress);
        $balanceInWei = $this->toPlainString($balanceInWei);

        if (!is_numeric($balanceInWei) || $balanceInWei === '0') {
            return $this->apiResponse(false, 'Token balance is invalid or zero');
        }

        $decimals = 18;
        if ($isFullOut && $amount === null) {
            $amountInWei = $balanceInWei;
        } else {
            if (!is_numeric($amount)) {
                return $this->apiResponse(false, 'Amount must be a valid number');
            }
            $amountInWei = bcmul((string)$amount, bcpow('10', (string)$decimals), 0);
            $amountInWei = $this->toPlainString($amountInWei);

            if (!is_numeric($amountInWei) || bccomp($balanceInWei, $amountInWei) < 0) {
                return $this->apiResponse(false, 'Insufficient token balance to send the specified amount');
            }
        }

        // Gas limit fixed to 60000
        $gasLimit = 60000;

        // Get current gas price from network but cap max at 1 Gwei (1_000_000_000 wei)
        $networkGasPrice = $this->getGasPrice($rpcUrl);
        $maxGasPrice = 1000000000; // 1 Gwei

        $gasPrice = ($networkGasPrice > $maxGasPrice) ? $maxGasPrice : $networkGasPrice;

        $gasFeeInWei = bcmul((string)$gasLimit, (string)$gasPrice);

        // Send gas fee to sender address from admin if needed
        $this->sendGasFee($rpcUrl, $senderAddress, $gasFeeInWei, $adminKey, $chainId, $adminAddress);

        usleep(500000);

        $nativeBalance = $this->getNativeBalance($rpcUrl, $senderAddress);
        if (bccomp($nativeBalance, $gasFeeInWei) < 0) {
            return $this->apiResponse(false, 'Gas fee not received by sender address');
        }

        // Prepare token transfer data
        $data = '0xa9059cbb' .
            str_pad(substr($this->removeHexPrefix($toAddress), 0, 64), 64, '0', STR_PAD_LEFT) .
            str_pad($this->bcdechex($amountInWei), 64, '0', STR_PAD_LEFT);

        $nonce = $this->getNonce($rpcUrl, $senderAddress);
        $nonceHex = $this->decToHex($nonce);

        $transaction = [
            'nonce' => '0x' . $nonceHex,
            'from' => $senderAddress,
            'to' => $tokenAddress,
            'value' => '0x0',
            'gas' => '0x' . $this->decToHex((string)$gasLimit),
            'gasPrice' => '0x' . $this->decToHex((string)$gasPrice),
            'data' => $data,
            'chainId' => $chainId,
        ];

        $tx = new Transaction($transaction);
        $signedTx = $tx->sign($userKey);
        $txHash = $this->sendRawTransaction($rpcUrl, $signedTx);

        for ($i = 0; $i < 10; $i++) {
            $receipt = $this->getTransactionReceipt($rpcUrl, $txHash);
            if ($receipt && isset($receipt['status']) && hexdec($receipt['status']) === 1) {
                return $this->apiResponse(true, 'Transaction sent successfully.', $txHash, number_format(bcdiv($amountInWei, bcpow('10', '18'), 4), 4, '.', ''));
            }
            usleep(500000);
        }

        return $this->apiResponse(false, 'Transaction sent but not confirmed after retries');
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
        $nonceHex = $this->decToHex($nonce);

        // Fixed gas limit 60000 and gas price capped at 1 Gwei
        $gasLimit = 60000;
        $maxGasPrice = 1000000000; // 1 Gwei
        $networkGasPrice = $this->getGasPrice($rpcUrl);
        $gasPrice = ($networkGasPrice > $maxGasPrice) ? $maxGasPrice : $networkGasPrice;

        $currentBalance = $this->toPlainString($this->getNativeBalance($rpcUrl, $toAddress));
        $totalNeeded = $this->toPlainString($estimatedGasFee);

        if (bccomp($currentBalance, $totalNeeded) >= 0) {
            return null; // no need to top up
        }

        $requiredTopUp = $this->toPlainString(bcsub($totalNeeded, $currentBalance));

        $transaction = [
            'nonce' => '0x' . $nonceHex,
            'from' => $adminAddress,
            'to' => $toAddress,
            'value' => '0x' . $this->decToHex($requiredTopUp),
            'gas' => '0x' . $this->decToHex((string)$gasLimit),
            'gasPrice' => '0x' . $this->decToHex((string)$gasPrice),
            'chainId' => $chainId
        ];

        $tx = new Transaction($transaction);
        $signedTx = $tx->sign($adminKey);
        $txHash = $this->sendRawTransaction($rpcUrl, $signedTx);

        return $txHash;
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
        return $this->hexToDec($this->removeHexPrefix($response['result']));
    }

    private function getNativeBalance($rpcUrl, $address): string
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_getBalance',
            'params' => [$address, 'latest'],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        return $this->hexToDec($this->removeHexPrefix($response['result']));
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
            'id' => 1,
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);

        return $this->hexToDec($this->removeHexPrefix($response['result']));
    }

    private function toPlainString($number)
    {
        if (!is_numeric($number)) return '0';

        if (stripos($number, 'e') !== false) {
            return number_format($number, 0, '', '');
        }

        return (string) $number;
    }

    private function getNonce($rpcUrl, $address): string
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionCount',
            'params' => [$address, 'pending'],  // অবশ্যই 'pending' ব্যবহার করবেন
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);

        return $this->hexToDec($this->removeHexPrefix($response['result']));
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

        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            error_log("Invalid transaction hash returned: " . $txHash);
            throw new \Exception("Invalid transaction hash returned: " . $txHash);
        }

        return $txHash;
    }

    public function getTransactionReceipt($rpcUrl, $txHash)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method'  => 'eth_getTransactionReceipt',
                    'params'  => [$txHash],
                    'id'      => 1,
                ],
                'timeout' => 10,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['result']) && $result['result']) {
                return $result['result'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function apiResponse(bool $status, string $message, $txHash = null, $amount  = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'txHash' => $txHash,
            'amount' => $amount,
        ];
    }

    // Convert large hex string to decimal string using BCMath
    private function hexToDec(string $hex): string
    {
        $hex = strtolower($hex);
        $dec = '0';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $current = strpos('0123456789abcdef', $hex[$i]);
            $dec = bcmul($dec, '16', 0);
            $dec = bcadd($dec, $current, 0);
        }
        return $dec;
    }

    // Convert large decimal string to hex string using BCMath
    private function decToHex(string $dec): string
    {
        $hex = '';
        while (bccomp($dec, '0') > 0) {
            $mod = bcmod($dec, '16');
            $hex = dechex((int)$mod) . $hex;
            $dec = bcdiv($dec, '16', 0);
        }
        return $hex === '' ? '0' : $hex;
    }

    private function bcdechex($dec)
    {
        return $this->decToHex($dec);
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
