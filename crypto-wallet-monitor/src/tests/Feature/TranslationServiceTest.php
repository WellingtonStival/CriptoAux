<?php

namespace Tests\Feature;

use App\Services\Translation\TranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    public function test_returns_original_texts_when_no_api_key_is_configured(): void
    {
        config(['translation.deepl.api_key' => null]);

        $texts = ['Bitcoin hits new high', 'Ethereum upgrade goes live'];

        $result = app(TranslationService::class)->translateToPortuguese($texts);

        $this->assertSame($texts, $result);
        Http::assertNothingSent();
    }

    public function test_returns_empty_array_for_empty_input(): void
    {
        config(['translation.deepl.api_key' => 'fake-key']);

        $result = app(TranslationService::class)->translateToPortuguese([]);

        $this->assertSame([], $result);
    }

    public function test_translates_texts_when_the_api_key_is_configured(): void
    {
        config(['translation.deepl.api_key' => 'fake-key']);

        Http::fake([
            '*' => Http::response([
                'translations' => [
                    ['text' => 'Bitcoin atinge nova máxima'],
                    ['text' => 'Atualização do Ethereum entra no ar'],
                ],
            ]),
        ]);

        $result = app(TranslationService::class)->translateToPortuguese([
            'Bitcoin hits new high',
            'Ethereum upgrade goes live',
        ]);

        $this->assertSame([
            'Bitcoin atinge nova máxima',
            'Atualização do Ethereum entra no ar',
        ], $result);
    }

    public function test_falls_back_to_original_texts_when_the_request_fails(): void
    {
        config(['translation.deepl.api_key' => 'fake-key']);

        Http::fake(['*' => Http::response([], 500)]);

        $texts = ['Bitcoin hits new high'];

        $result = app(TranslationService::class)->translateToPortuguese($texts);

        $this->assertSame($texts, $result);
    }

    public function test_falls_back_to_original_texts_when_the_translation_count_does_not_match(): void
    {
        config(['translation.deepl.api_key' => 'fake-key']);

        Http::fake([
            '*' => Http::response([
                'translations' => [
                    ['text' => 'Só uma tradução'],
                ],
            ]),
        ]);

        $texts = ['Bitcoin hits new high', 'Ethereum upgrade goes live'];

        $result = app(TranslationService::class)->translateToPortuguese($texts);

        $this->assertSame($texts, $result);
    }
}
