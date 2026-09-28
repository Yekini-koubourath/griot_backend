<?php

namespace App\Services;

use App\Models\CompteSocial;
use App\Models\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FacebookPublisher
{
    private const GRAPH = 'https://graph.facebook.com/v24.0';

    /**
     * Publie sur la Page Facebook du projet. Retourne l'ID du post.
     */
    public function publish(Publication $publication): string
    {
        $compte = CompteSocial::where('project_id', $publication->project_id)
            ->where('reseau', 'facebook')
            ->where('statut', 'actif')
            ->first();

        if (!$compte) {
            throw new RuntimeException('Aucune Page Facebook connectée pour ce projet.');
        }

        $pageId = $compte->compte_id;
        $token = $compte->access_token; // déchiffré par le cast "encrypted"
        $image = $this->resolveImage($publication);

        if ($image) {
            $response = Http::attach('source', $image['contents'], $image['name'])
                ->post(self::GRAPH . "/{$pageId}/photos", [
                    'caption' => $publication->content,
                    'access_token' => $token,
                    'published' => 'true',
                ]);
        } else {
            $response = Http::asForm()->post(self::GRAPH . "/{$pageId}/feed", [
                'message' => $publication->content,
                'access_token' => $token,
            ]);
        }

        if (!$response->successful()) {
            $error = $response->json('error', []);

            \Log::error('Facebook publish error', ['response' => $response->json()]);

            // Code 190 = token expiré ou révoqué
            if (($error['code'] ?? null) === 190) {
                $compte->update(['statut' => 'expire']);
            }

            throw new RuntimeException(
                'Facebook a refusé la publication : ' . ($error['message'] ?? 'erreur inconnue')
            );
        }

        return (string) ($response->json('post_id') ?? $response->json('id'));
    }

    /**
     * Retourne le contenu binaire de l'image à envoyer, ou null.
     * Facebook ne peut pas lire une URL localhost : on envoie le fichier lui-même.
     */
    private function resolveImage(Publication $publication): ?array
    {
        $image = $publication->image;

        // 1. Image base64 ajoutée depuis l'aperçu
        if (is_string($image) && preg_match('#^data:image/(\w+);base64,(.+)$#s', $image, $m)) {
            $contents = base64_decode($m[2], true);

            if ($contents !== false) {
                return ['contents' => $contents, 'name' => 'image.' . $m[1]];
            }
        }

        // 2. URL pointant vers notre propre stockage (/storage/...)
        if (is_string($image) && str_contains($image, '/storage/')) {
            $relative = ltrim(explode('/storage/', $image, 2)[1], '/');

            if (Storage::disk('public')->exists($relative)) {
                return [
                    'contents' => Storage::disk('public')->get($relative),
                    'name' => basename($relative),
                ];
            }
        }

        // 3. Premier média image de la bibliothèque
        $media = $publication->medias->firstWhere('type', 'image');

        if ($media && $media->path && Storage::disk('public')->exists($media->path)) {
            return [
                'contents' => Storage::disk('public')->get($media->path),
                'name' => basename($media->path),
            ];
        }

        return null;
    }
}