<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    public const TYPE_WALLET_BALANCE_DROP = 'wallet_balance_drop';
    public const TYPE_PORTFOLIO_CHANGE = 'portfolio_change';
    public const TYPE_PRICE_CHANGE = 'price_change';

    protected $fillable = [
        'user_id',
        'type',
        'wallet_id',
        'network',
        'threshold_percent',
        'direction',
        'is_active',
    ];

    protected $casts = [
        'threshold_percent' => 'float',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
