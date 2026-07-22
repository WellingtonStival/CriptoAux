<?php

namespace App\Http\Controllers;

use App\Services\Blockchain\BlockchainResolver;
use App\Services\News\NewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request, NewsService $newsService)
    {
        $network = $request->query('network');

        if ($network !== null && !in_array($network, BlockchainResolver::supportedNetworks(), true)) {
            return response()->json([
                'message' => 'Rede não suportada.',
            ], 422);
        }

        return response()->json([
            'network' => $network,
            'news' => $newsService->latest($network),
        ]);
    }
}
