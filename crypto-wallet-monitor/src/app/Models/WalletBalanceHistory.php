<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletBalanceHistory extends Model
{
    protected $fillable = [
        'wallet_id',
        'network',
        'balance',
        'price_usd',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'balance' => 'float',
        'price_usd' => 'float',
    ];

    public $timestamps = true;
}
