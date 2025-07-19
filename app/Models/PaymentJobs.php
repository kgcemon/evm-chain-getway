<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class PaymentJobs extends Model
{
    protected $table = 'payment_jobs';
    protected $fillable = [
        'token_name',
        'chain_id',
        'wallet_address',
        'status',
        'key',
        'webhook_url',
        'rpc_url',
        'type',
        'contract_address',
    ];
}
