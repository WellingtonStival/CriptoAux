<?php

namespace App\Http\Controllers;

use App\Services\Market\PriceService;

class PriceController extends Controller
{
    public function index(PriceService $prices)
    {
        return response()->json($prices->current());
    }
}
