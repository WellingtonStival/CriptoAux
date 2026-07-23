<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Services\Blockchain\BlockchainResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    private const TYPES = [
        AlertRule::TYPE_WALLET_BALANCE_DROP,
        AlertRule::TYPE_PORTFOLIO_CHANGE,
        AlertRule::TYPE_PRICE_CHANGE,
    ];

    public function index(Request $request)
    {
        $rules = $request->user()->alertRules()->with('wallet')->latest()->get();

        return response()->json(['alerts' => $rules->map(fn (AlertRule $rule) => $this->present($rule))]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'wallet_id' => [
                'nullable',
                Rule::exists('wallets', 'id')->where('user_id', $user->id),
            ],
            'network' => [
                'nullable',
                Rule::in(BlockchainResolver::supportedNetworks()),
            ],
            'threshold_percent' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'direction' => ['nullable', Rule::in(['down', 'up', 'any'])],
        ]);

        if ($validated['type'] === AlertRule::TYPE_PRICE_CHANGE && empty($validated['network'])) {
            return response()->json([
                'message' => 'Selecione uma moeda para esse tipo de alerta.',
                'errors' => ['network' => ['Campo obrigatório para alertas de preço.']],
            ], 422);
        }

        $rule = $user->alertRules()->create([
            'type' => $validated['type'],
            'wallet_id' => $validated['type'] === AlertRule::TYPE_WALLET_BALANCE_DROP
                ? ($validated['wallet_id'] ?? null)
                : null,
            'network' => $validated['type'] === AlertRule::TYPE_PRICE_CHANGE
                ? $validated['network']
                : null,
            'threshold_percent' => $validated['threshold_percent'],
            'direction' => $validated['type'] === AlertRule::TYPE_WALLET_BALANCE_DROP
                ? 'down'
                : ($validated['direction'] ?? 'down'),
            // Explicito em vez de depender do default da coluna no banco -
            // logo apos create(), o objeto em memoria nao reflete defaults
            // de banco que nao foram passados (bug real, pego testando ao
            // vivo: a API respondia is_active=null pro frontend, que
            // mostrava o alerta recem-criado como "pausado").
            'is_active' => true,
        ]);

        return response()->json($this->present($rule->load('wallet')), 201);
    }

    public function update(Request $request, $id)
    {
        $rule = $request->user()->alertRules()->findOrFail($id);

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'threshold_percent' => ['sometimes', 'numeric', 'min:0.1', 'max:100'],
        ]);

        $rule->update($validated);

        return response()->json($this->present($rule->load('wallet')));
    }

    public function destroy(Request $request, $id)
    {
        $rule = $request->user()->alertRules()->findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Alerta removido.']);
    }

    private function present(AlertRule $rule): array
    {
        return [
            'id' => $rule->id,
            'type' => $rule->type,
            'wallet_id' => $rule->wallet_id,
            'wallet_label' => $rule->wallet?->name ?: $rule->wallet?->address,
            'network' => $rule->network,
            'threshold_percent' => $rule->threshold_percent,
            'direction' => $rule->direction,
            'is_active' => $rule->is_active,
            'last_triggered_at' => $rule->last_triggered_at?->toIso8601String(),
        ];
    }
}
