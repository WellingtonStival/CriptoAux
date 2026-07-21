<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'address' => '0x' . fake()->regexify('[a-fA-F0-9]{40}'),
            'network' => 'ethereum',
        ];
    }
}
