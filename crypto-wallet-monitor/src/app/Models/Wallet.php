<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WalletBalanceHistory;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
		'address',
		'name',
		'user_id',
		'network',
	];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
	public function balanceHistories()
	{
		return $this->hasMany(WalletBalanceHistory::class);
	}

	public function tokens()
	{
		return $this->hasMany(WalletToken::class);
	}
}
