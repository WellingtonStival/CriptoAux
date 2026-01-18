<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletBalanceHistory extends Model
{
    protected $fillable = [
        'wallet_id',
        'network',
        'balance',
        'captured_at',
    ];

    public $timestamps = true;
}
