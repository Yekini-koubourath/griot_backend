<?php

namespace App\Http\Controllers;

use Gemini\Contracts\ClientContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function test(
    Request $request,
    ClientContract $gemini
): JsonResponse {
    $request->validate([
        'message' => ['required', 'string', 'max:5000'],
    ]);

    try {
        $response = $gemini
            ->generativeModel(model: 'gemini-3.6-flash')
            ->generateContent(
                $request->input('message')
            );

        return response()->json([
            'success' => true,
            'message' => $response->text(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Le service IA est momentanément très sollicité. Veuillez réessayer dans quelques secondes.',
        ], 503);
    }
}
}