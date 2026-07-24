<?php

namespace App\Http\Controllers;

use App\Services\Security\ApprovalScanService;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    private const RISK_ORDER = ['alta' => 0, 'media' => 1, 'baixa' => 2];

    /**
     * Varre as aprovacoes de token (ERC-20 approve/allowance) de todas as
     * wallets EVM do usuario - acao sob demanda (nao persiste nada, nao
     * roda em background), mesmo racional do "Buscar tokens": e uma
     * chamada mais pesada a um servico terceiro, so dispara quando o
     * usuario pede.
     */
    public function approvals(Request $request, ApprovalScanService $scanner)
    {
        $wallets = $request->user()->wallets()
            ->whereIn('network', ['ethereum', 'polygon', 'bnb', 'avalanche', 'arbitrum'])
            ->get(['id', 'name', 'address', 'network']);

        $approvals = collect();

        foreach ($wallets as $wallet) {
            foreach ($scanner->scan($wallet->network, $wallet->address) as $approval) {
                $approval['wallet_id'] = $wallet->id;
                $approval['wallet_label'] = $wallet->name ?: $wallet->address;
                $approvals->push($approval);
            }
        }

        $sorted = $approvals
            ->sortBy(fn (array $approval) => self::RISK_ORDER[$approval['risk']] ?? 3)
            ->values();

        return response()->json([
            'approvals' => $sorted,
            'summary' => [
                'total' => $sorted->count(),
                'high_risk' => $sorted->where('risk', 'alta')->count(),
                'scanned_wallets' => $wallets->count(),
            ],
        ]);
    }
}
