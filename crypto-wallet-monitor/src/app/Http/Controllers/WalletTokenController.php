<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletToken;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Blockchain\Contracts\TokenDiscoveryProvider;
use App\Services\Market\PriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletTokenController extends Controller
{
    /**
     * Nenhum token real tem saldo nominal (ja dividido pelas decimals) acima
     * disso - tokens de spam/scam mintam saldos proximos do limite do
     * uint256 pra chamar atencao em exploradores. Sem esse filtro, alem de
     * poluir a lista, um saldo desses (~10^59) estoura qualquer coluna
     * decimal razoavel do Postgres.
     */
    private const SPAM_BALANCE_THRESHOLD = 1e15;

    /**
     * Lista os tokens ja rastreados nessa wallet (com o ultimo saldo/valor
     * salvo) - nao busca nada ao vivo, so le o que ja foi sincronizado.
     */
    public function index(Request $request, $walletId)
    {
        $wallet = Wallet::where('id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $tokens = $wallet->tokens()->get()->map(fn (WalletToken $token) => $this->present($token));

        return response()->json(['tokens' => $tokens]);
    }

    /**
     * Descobre e atualiza os tokens dessa wallet ao vivo. E uma acao mais
     * pesada que a consulta normal de saldo (chama um indexador terceiro
     * no caso do Ethereum) - por isso e um endpoint separado, disparado
     * so quando o usuario pede ("Buscar tokens"), nao a cada carregamento
     * de tela.
     */
    public function sync(Request $request, $walletId, BlockchainResolver $resolver, PriceService $priceService)
    {
        $wallet = Wallet::where('id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $service = $resolver->resolve($wallet->network);

        if (!$service instanceof TokenDiscoveryProvider) {
            return response()->json([
                'message' => 'Essa rede ainda não suporta tokens.',
            ], 422);
        }

        $discovered = collect($service->discoverTokens($wallet->address))
            ->filter(function (array $tokenData) use ($wallet) {
                if ($tokenData['balance'] <= self::SPAM_BALANCE_THRESHOLD) {
                    return true;
                }

                Log::warning('Token ignorado por saldo nominal implausivel (provavel spam)', [
                    'wallet_id' => $wallet->id,
                    'contract_address' => $tokenData['contract_address'],
                    'balance' => $tokenData['balance'],
                ]);

                return false;
            })
            ->values();

        $contractAddresses = collect($discovered)->pluck('contract_address')->all();
        $prices = $priceService->tokenPrices($wallet->network, $contractAddresses);

        $tokens = collect($discovered)->map(function (array $tokenData) use ($wallet, $prices) {
            $walletToken = WalletToken::updateOrCreate(
                [
                    'wallet_id' => $wallet->id,
                    'contract_address' => $tokenData['contract_address'],
                ],
                [
                    'symbol' => $tokenData['symbol'],
                    'name' => $tokenData['name'],
                    'logo_url' => $tokenData['logo_url'] ?? null,
                    'decimals' => $tokenData['decimals'],
                ]
            );

            $priceUsd = $prices[strtolower($tokenData['contract_address'])] ?? null;

            $walletToken->balanceHistories()->create([
                'balance' => $tokenData['balance'],
                'price_usd' => $priceUsd,
                'captured_at' => now(),
            ]);

            return $this->present($walletToken->fresh());
        });

        Log::info('Tokens sincronizados', ['wallet_id' => $wallet->id, 'count' => $tokens->count()]);

        return response()->json(['tokens' => $tokens->values()]);
    }

    public function destroy(Request $request, $walletId, $tokenId)
    {
        $wallet = Wallet::where('id', $walletId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $token = $wallet->tokens()->findOrFail($tokenId);
        $token->delete();

        return response()->json(['message' => 'Token removido.']);
    }

    private function present(WalletToken $token): array
    {
        $latest = $token->balanceHistories()->latest('captured_at')->first();
        $balance = $latest->balance ?? 0.0;
        $priceUsd = $latest->price_usd ?? null;

        return [
            'id' => $token->id,
            'contract_address' => $token->contract_address,
            'symbol' => $token->symbol,
            'name' => $token->name,
            'logo_url' => $token->logo_url,
            'decimals' => $token->decimals,
            'balance' => $balance,
            'price_usd' => $priceUsd,
            'value_usd' => $priceUsd !== null ? $balance * $priceUsd : null,
        ];
    }
}
