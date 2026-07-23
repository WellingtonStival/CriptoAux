<?php

namespace Tests\Feature;

use App\Services\Market\FearGreedService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FearGreedServiceTest extends TestCase
{
    private function fakeResponse(): array
    {
        return [
            'name' => 'Fear and Greed Index',
            'data' => [
                ['value' => '31', 'value_classification' => 'Fear', 'timestamp' => '1784764800'],
                ['value' => '25', 'value_classification' => 'Extreme Fear', 'timestamp' => '1784678400'],
            ],
            'metadata' => ['error' => null],
        ];
    }

    public function test_current_returns_the_latest_point_translated_to_pt(): void
    {
        Http::fake(['*' => Http::response($this->fakeResponse())]);

        $current = app(FearGreedService::class)->current();

        $this->assertSame(31, $current['value']);
        $this->assertSame('Medo', $current['classification']);
    }

    public function test_history_returns_points_oldest_first(): void
    {
        Http::fake(['*' => Http::response($this->fakeResponse())]);

        $points = app(FearGreedService::class)->history(2);

        $this->assertCount(2, $points);
        $this->assertSame(25, $points[0]['value']);
        $this->assertSame('Medo Extremo', $points[0]['classification']);
        $this->assertSame(31, $points[1]['value']);
    }

    public function test_caches_the_response_and_does_not_repeat_the_request(): void
    {
        Http::fake(['*' => Http::response($this->fakeResponse())]);

        $service = app(FearGreedService::class);
        $service->current();
        $service->current();

        Http::assertSentCount(1);
    }

    public function test_returns_null_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertNull(app(FearGreedService::class)->current());
    }
}
