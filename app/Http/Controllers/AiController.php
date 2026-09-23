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
    | Réseaux autorisés + règles d'écriture par réseau
    |--------------------------------------------------------------------------
    |
    | Ce tableau est LE contexte donné à Gemini pour qu'il sache comment
    | rédiger pour chaque plateforme. Modifiez ces lignes pour ajuster le
    | style attendu sans toucher au reste du contrôleur.
    */
    private const NETWORK_GUIDELINES = [
        'facebook' => "Facebook : ton chaleureux et conversationnel, 2 à 5 phrases, quelques emojis pertinents, se termine idéalement par une question ou un appel à l'action.",
        'instagram' => "Instagram : texte accrocheur, phrases courtes et rythmées, emojis pertinents, se termine par 5 à 10 hashtags pertinents séparés par des espaces.",
        'linkedin' => "LinkedIn : ton professionnel et structuré, peut utiliser des retours à la ligne pour aérer, apporte une vraie valeur ou une réflexion, emojis discrets ou absents, 3 à 8 phrases.",
        'tiktok' => "TikTok : texte très court et percutant (1 à 3 phrases), langage direct et dynamique, se termine par 3 à 5 hashtags courts.",
        'x' => "X (Twitter) : texte concis, 280 caractères maximum, direct et percutant, 1 à 2 hashtags maximum.",
        'google' => "Google Business : ton informatif et local, 2 à 4 phrases, met en avant une information pratique, une offre ou une actualité, sans hashtags.",
    ];

    /*
    |--------------------------------------------------------------------------
    | Génération de texte (chat libre, utilisé par GriotAiChat)
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
    | Génération directe du contenu d'une publication, pour chaque réseau
    | sélectionné, à partir de l'idée / titre / ton / langue choisis par
    | l'utilisateur sur la page de création de publication.
    |--------------------------------------------------------------------------
    */

    public function generatePublication(
        Request $request,
        ClientContract $gemini
    ): JsonResponse {
        $validated = $request->validate([
            'idea' => ['required', 'string', 'max:5000'],
            'title' => ['nullable', 'string', 'max:255'],
            'tone' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:50'],
            'networks' => ['required', 'array', 'min:1'],
            'networks.*' => [
                'string',
                'in:facebook,instagram,linkedin,tiktok,x,google',
            ],
        ]);

        $idea = $validated['idea'];
        $title = $validated['title'] ?? '';
        $tone = $validated['tone'] ?? 'Professionnel & motivant';
        $language = $validated['language'] ?? 'Français';
        $networks = array_values(array_unique($validated['networks']));

        $selectedGuidelines = collect($networks)
            ->map(fn ($id) => '- ' . (self::NETWORK_GUIDELINES[$id] ?? $id))
            ->implode("\n");

        $networksJsonKeys = collect($networks)
            ->map(fn ($id) => "\"{$id}\"")
            ->implode(', ');

        /*
        |----------------------------------------------------------------
        | CONTEXTE DONNÉ À GEMINI
        |----------------------------------------------------------------
        |
        | C'est ici que Gemini apprend ce qu'est l'application et ce
        | qu'on attend exactement de lui : un JSON strict, un contenu
        | par réseau, adapté aux codes de chaque plateforme, prêt à
        | publier tel quel.
        */
        $prompt = <<<PROMPT
Tu es Griot AI, l'assistant IA intégré à l'application Griot AI, un outil qui aide des entreprises et créateurs de contenu à rédiger et publier leurs posts sur les réseaux sociaux (Facebook, Instagram, LinkedIn, TikTok, X, Google Business).

CONTEXTE DE LA DEMANDE
Titre de la publication : "{$title}"
Idée / sujet donné par l'utilisateur : "{$idea}"
Ton souhaité : {$tone}
Langue de rédaction : {$language}

TA MISSION
Rédige le texte complet et prêt à être publié tel quel, pour chacun des réseaux sociaux suivants, en respectant les codes propres à chaque plateforme :
{$selectedGuidelines}

RÈGLES STRICTES
- Rédige un contenu complet et directement publiable, jamais de placeholder du type "[à compléter]".
- N'ajoute aucun commentaire, aucune explication, aucun texte avant ou après le contenu demandé.
- Respecte scrupuleusement la langue demandée ({$language}) et le ton demandé ({$tone}).
- Chaque réseau doit avoir un texte réellement différent et adapté à son format, jamais un simple copier-coller d'un réseau à l'autre.
- Réponds UNIQUEMENT avec un objet JSON valide (aucun texte autour, aucune balise markdown, aucun bloc de code), contenant EXACTEMENT ces clés : {$networksJsonKeys}

Format de réponse attendu (exemple) :
{"facebook": "texte ici", "instagram": "texte ici"}
PROMPT;

        try {
            $response = $gemini
                ->generativeModel(model: 'gemini-3.6-flash')
                ->generateContent($prompt);

            $raw = trim($response->text());

            // Sécurité : au cas où le modèle entourerait sa réponse de ```json ... ```
            $raw = preg_replace('/^```(?:json)?/i', '', $raw);
            $raw = preg_replace('/```$/', '', $raw);
            $raw = trim($raw);

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                \Log::warning('Réponse IA non-JSON pour generatePublication', [
                    'raw' => $raw,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Griot AI n'a pas pu générer un contenu exploitable. Réessayez.",
                ], 500);
            }

            $contents = [];

            foreach ($networks as $networkId) {
                if (
                    !empty($decoded[$networkId]) &&
                    is_string($decoded[$networkId])
                ) {
                    $contents[$networkId] = $decoded[$networkId];
                }
            }

            if (empty($contents)) {
                return response()->json([
                    'success' => false,
                    'message' => "Griot AI n'a généré aucun contenu utilisable pour les réseaux sélectionnés.",
                ], 500);
            }

            return response()->json([
                'success' => true,
                'contents' => $contents,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Erreur génération publication IA', [
                'error' => $e->getMessage(),
            ]);

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