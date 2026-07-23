<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTokenBalanceHistory extends Model
{
    protected $fillable = [
        'wallet_token_id',
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

    public function walletToken()
    {
        return $this->belongsTo(WalletToken::class);
    }
}
