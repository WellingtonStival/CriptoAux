<?php

namespace Tests\Feature;

use App\Services\Market\PriceService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    public function test_returns_price_and_24h_change_for_each_supported_coin(): void
    {
        Http::fake([
            '*' => Http::response([
                'ethereum' => ['usd' => 3245.67, 'usd_24h_change' => 2.34],
                'solana' => ['usd' => 145.23, 'usd_24h_change' => -1.12],
                'bitcoin' => ['usd' => 65432.10, 'usd_24h_change' => 0.87],
            ]),
        ]);

        $prices = app(PriceService::class)->current();

        $this->assertSame(3245.67, $prices['ethereum']['usd']);
        $this->assertSame(2.34, $prices['ethereum']['change_24h']);
        $this->assertSame(-1.12, $prices['solana']['change_24h']);
        $this->assertSame(65432.10, $prices['bitcoin']['usd']);
    }

    public function test_caches_the_prices_and_does_not_repeat_the_request(): void
    {
        Http::fake([
            '*' => Http::response([
                'ethereum' => ['usd' => 3245.67, 'usd_24h_change' => 2.34],
                'solana' => ['usd' => 145.23, 'usd_24h_change' => -1.12],
                'bitcoin' => ['usd' => 65432.10, 'usd_24h_change' => 0.87],
            ]),
        ]);

        $service = app(PriceService::class);

        $service->current();
        $service->current();

        Http::assertSentCount(1);
    }

    public function test_aborts_with_502_when_the_request_fails(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        try {
            app(PriceService::class)->current();

            $this->fail('Esperava uma HttpException com status 502.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }
}
