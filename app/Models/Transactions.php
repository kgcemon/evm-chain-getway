<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transactions extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'chain_id',
        'amount',
        'token_name',
        'status',
        'trx_hash',
        'type'
    ];
}
