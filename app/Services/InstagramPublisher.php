<?php

namespace App\Services;

use App\Models\CompteSocial;
use App\Models\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InstagramPublisher
{
    private const GRAPH = 'https://graph.facebook.com/v24.0';

    /**
     * Publie sur le compte Instagram Business du projet.
     * Retourne l'ID du média publié.
     */
    public function publish(Publication $publication): string
    {
        $compte = CompteSocial::where('project_id', $publication->project_id)
            ->where('reseau', 'instagram')
            ->where('statut', 'actif')
            ->first();

        if (!$compte) {
            throw new RuntimeException(
                'Aucun compte Instagram connecté pour ce projet. '
                . 'Connectez Facebook avec une Page liée à un compte Instagram professionnel.'
            );
        }

        $igUserId = $compte->compte_id;
        $token = $compte->access_token;

        $videoUrl = $this->resolvePublicVideoUrl($publication);
        $imageUrl = $videoUrl ? null : $this->resolvePublicImageUrl($publication);

        if (!$videoUrl && !$imageUrl) {
            throw new RuntimeException(
                'Instagram nécessite une image ou une vidéo pour publier.'
            );
        }

        $createParams = [
            'caption' => $publication->content,
            'access_token' => $token,
        ];

        if ($videoUrl) {
            $createParams['media_type'] = 'REELS';
            $createParams['video_url'] = $videoUrl;
        } else {
            $createParams['image_url'] = $imageUrl;
        }

        $createResponse = Http::asForm()->post(
            self::GRAPH . "/{$igUserId}/media",
            $createParams
        );

        if (!$createResponse->successful()) {
            $this->handleError(
                $createResponse,
                $compte,
                'Instagram a refusé la préparation du contenu.'
            );
        }

        $creationId = $createResponse->json('id');

        if (!$creationId) {
            throw new RuntimeException(
                "Instagram n'a pas retourné d'identifiant de création."
            );
        }

        if ($videoUrl) {
            $this->waitUntilReady($creationId, $token, $compte);
        }

        $publishResponse = Http::asForm()->post(
            self::GRAPH . "/{$igUserId}/media_publish",
            [
                'creation_id' => $creationId,
                'access_token' => $token,
            ]
        );

        if (!$publishResponse->successful()) {
            $this->handleError(
                $publishResponse,
                $compte,
                'Instagram a refusé la publication.'
            );
        }

        return (string) $publishResponse->json('id');
    }

    /**
     * Attend que la vidéo soit traitée côté Instagram avant publication.
     */
    private function waitUntilReady(
        string $creationId,
        string $token,
        CompteSocial $compte
    ): void {
        for ($i = 0; $i < 20; $i++) {
            $response = Http::get(self::GRAPH . "/{$creationId}", [
                'fields' => 'status_code',
                'access_token' => $token,
            ]);

            $status = $response->json('status_code');

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                throw new RuntimeException(
                    'Le traitement de la vidéo Instagram a échoué.'
                );
            }

            sleep(3);
        }

        throw new RuntimeException(
            'Le traitement de la vidéo Instagram prend trop de temps.'
        );
    }

    /**
     * Instagram exige une URL publique (pas de base64, pas d'upload direct).
     * On sauvegarde donc l'image dans le stockage public si besoin,
     * puis on construit une URL absolue.
     */
    private function resolvePublicImageUrl(Publication $publication): ?string
    {
        $image = $publication->image;

        if (
            is_string($image)
            && preg_match('#^data:image/(\w+);base64,(.+)$#s', $image, $m)
        ) {
            $contents = base64_decode($m[2], true);

            if ($contents === false) {
                return null;
            }

            $path = 'instagram/' . Str::uuid() . '.' . $m[1];

            Storage::disk('public')->put($path, $contents);

            return $this->publicUrl($path);
        }

        if (is_string($image) && str_contains($image, '/storage/')) {
            return Str::startsWith($image, 'http')
                ? $image
                : rtrim(config('app.url'), '/') . $image;
        }

        $media = $publication->medias->firstWhere('type', 'image');

        if ($media && $media->path) {
            return $this->publicUrl($media->path);
        }

        return null;
    }

    private function resolvePublicVideoUrl(Publication $publication): ?string
    {
        $media = $publication->medias->first(function ($media) {
            return $media->type === 'video'
                || str_starts_with(strtolower($media->mime_type ?? ''), 'video/');
        });

        if (!$media || !$media->path) {
            return null;
        }

        if (!Storage::disk('public')->exists($media->path)) {
            throw new RuntimeException(
                'Le fichier vidéo sélectionné est introuvable dans le stockage.'
            );
        }

        return $this->publicUrl($media->path);
    }

    private function publicUrl(string $path): string
    {
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    private function handleError($response, CompteSocial $compte, string $message): void
    {
        $error = $response->json('error', []);

        \Log::error('Instagram publish error', [
            'response' => $response->json(),
        ]);

        if (($error['code'] ?? null) === 190) {
            $compte->update(['statut' => 'expire']);
        }

        throw new RuntimeException(
            $message . ' ' . ($error['message'] ?? '')
        );
    }
}