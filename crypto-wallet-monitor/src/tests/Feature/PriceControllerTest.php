<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_prices(): void
    {
        $this->getJson('/api/prices')->assertStatus(401);
    }

    public function test_user_can_see_prices_of_supported_coins(): void
    {
        Http::fake([
            '*' => Http::response([
                ['id' => 'ethereum', 'current_price' => 3245.67, 'price_change_percentage_24h_in_currency' => 2.34],
                ['id' => 'solana', 'current_price' => 145.23, 'price_change_percentage_24h_in_currency' => -1.12],
                ['id' => 'bitcoin', 'current_price' => 65432.10, 'price_change_percentage_24h_in_currency' => 0.87],
            ]),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/prices');

        $response->assertStatus(200)
            ->assertJsonPath('ethereum.usd', 3245.67)
            ->assertJsonPath('solana.change_24h', -1.12)
            ->assertJsonPath('bitcoin.usd', 65432.10);
    }
}
