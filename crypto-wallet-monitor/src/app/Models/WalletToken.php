<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletToken extends Model
{
    protected $fillable = [
        'wallet_id',
        'contract_address',
        'symbol',
        'name',
        'logo_url',
        'decimals',
    ];

    protected $casts = [
        'decimals' => 'integer',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function balanceHistories()
    {
        return $this->hasMany(WalletTokenBalanceHistory::class);
    }
}
