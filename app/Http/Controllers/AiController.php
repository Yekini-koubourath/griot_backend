<?php

namespace App\Http\Controllers;

use Gemini\Contracts\ClientContract;
use Gemini\Data\GenerationConfig;
use Gemini\Data\ImageConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Gemini\Enums\ResponseModality;

class AiController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Génération de texte
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Génération d'image
    |--------------------------------------------------------------------------
    */

    public function image(
        Request $request,
        ClientContract $gemini
    ): JsonResponse {
        $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $response = $gemini
                ->generativeModel(
                    model: 'gemini-3.1-flash-image'
                )
                ->withGenerationConfig(
                    new GenerationConfig(
responseModalities: [
    ResponseModality::TEXT,
    ResponseModality::IMAGE,
],
                        imageConfig: new ImageConfig(
                            aspectRatio: '1:1'
                        )
                    )
                )
                ->generateContent(
                    $request->input('prompt')
                );

            foreach ($response->parts() as $part) {
                if ($part->inlineData !== null) {
                    return response()->json([
                        'success' => true,
                        'image' => [
                            'data' => $part->inlineData->data,
                            'mime_type' => $part->inlineData->mimeType,
                        ],
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Gemini n’a retourné aucune image.',
            ], 500);
       } catch (\Throwable $e) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 503);
}
    }
}