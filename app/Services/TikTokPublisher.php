<?php

namespace App\Services;

use App\Models\CompteSocial;
use App\Models\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TikTokPublisher
{
    private const API = 'https://open.tiktokapis.com';

    /**
     * Publie une vidéo sur TikTok.
     */
    public function publish(Publication $publication): string
    {
        /*
        |--------------------------------------------------------------------------
        | Récupération du compte TikTok
        |--------------------------------------------------------------------------
        */

        $compte = CompteSocial::where(
            'project_id',
            $publication->project_id
        )
            ->where('reseau', 'tiktok')
            ->where('statut', 'actif')
            ->first();

        if (!$compte) {
            throw new RuntimeException(
                'Aucun compte TikTok connecté pour ce projet.'
            );
        }

        $accessToken = $compte->access_token;

        if (!$accessToken) {
            throw new RuntimeException(
                'Le token TikTok est introuvable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Récupération de la vidéo
        |--------------------------------------------------------------------------
        */

        $video = $this->resolveVideo($publication);

        if (!$video) {
            throw new RuntimeException(
                'Aucune vidéo n’a été sélectionnée pour cette publication TikTok.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Informations du créateur TikTok
        |--------------------------------------------------------------------------
        */

        $creatorResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->post(
                self::API . '/v2/post/publish/creator_info/query/'
            );

        if (!$creatorResponse->successful()) {
            $this->handleTikTokError(
                $creatorResponse,
                $compte,
                'Impossible de récupérer les informations du compte TikTok.'
            );
        }

        $creatorData = $creatorResponse->json('data', []);

        /*
        |--------------------------------------------------------------------------
        | Confidentialité
        |--------------------------------------------------------------------------
        |
        | Pour les tests avec une application non auditée,
        | TikTok peut imposer SELF_ONLY.
        |--------------------------------------------------------------------------
        */

        $privacyOptions =
            $creatorData['privacy_level_options'] ?? [];

        if (in_array('SELF_ONLY', $privacyOptions, true)) {
            $privacyLevel = 'SELF_ONLY';
        } elseif (!empty($privacyOptions)) {
            $privacyLevel = $privacyOptions[0];
        } else {
            throw new RuntimeException(
                'TikTok n’a retourné aucune option de confidentialité disponible.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Paramètres du créateur
        |--------------------------------------------------------------------------
        */

        $disableComment =
            (bool) ($creatorData['comment_disabled'] ?? false);

        $disableDuet =
            (bool) ($creatorData['duet_disabled'] ?? false);

        $disableStitch =
            (bool) ($creatorData['stitch_disabled'] ?? false);

        /*
        |--------------------------------------------------------------------------
        | Durée maximale
        |--------------------------------------------------------------------------
        */

        $maxDuration =
            $creatorData['max_video_post_duration_sec']
            ?? null;

        if ($maxDuration !== null) {
            $duration = $this->getVideoDuration(
                $video['path']
            );

            if (
                $duration !== null
                && $duration > $maxDuration
            ) {
                throw new RuntimeException(
                    "La vidéo dure {$duration} secondes, "
                    . "mais TikTok autorise au maximum "
                    . "{$maxDuration} secondes pour ce compte."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du format
        |--------------------------------------------------------------------------
        */

        $mimeType = strtolower(
            $video['mime_type']
        );

        $allowedMimeTypes = [
            'video/mp4',
            'video/quicktime',
            'video/webm',
        ];

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException(
                'Format vidéo non pris en charge par TikTok. '
                . 'Utilisez MP4, MOV ou WebM.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Taille de la vidéo
        |--------------------------------------------------------------------------
        */

        $videoSize = $video['size'];

        if ($videoSize <= 0) {
            throw new RuntimeException(
                'La vidéo sélectionnée est vide ou invalide.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Taille maximale
        |--------------------------------------------------------------------------
        */

        $maxSize = 4 * 1024 * 1024 * 1024;

        if ($videoSize > $maxSize) {
            throw new RuntimeException(
                'La vidéo dépasse la taille maximale autorisée par TikTok.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Taille des morceaux
        |--------------------------------------------------------------------------
        |
        | 10 Mo :
        | - supérieur au minimum TikTok
        | - inférieur à la limite maximale
        |--------------------------------------------------------------------------
        */

        $chunkSize = 10 * 1024 * 1024;

        /*
        | Si la vidéo fait moins de 10 Mo,
        | on envoie tout en un seul morceau.
        */

        if ($videoSize < $chunkSize) {
            $chunkSize = $videoSize;
        }

        $totalChunks = max(
            1,
            (int) floor(
                $videoSize / $chunkSize
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Initialisation de la publication
        |--------------------------------------------------------------------------
        */

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post(
                self::API . '/v2/post/publish/video/init/',
                [
                    'post_info' => [
                        'title' =>
                            $this->buildCaption(
                                $publication
                            ),

                        'privacy_level' =>
                            $privacyLevel,

                        'disable_duet' =>
                            $disableDuet,

                        'disable_comment' =>
                            $disableComment,

                        'disable_stitch' =>
                            $disableStitch,

                        /*
                         * À true uniquement si la vidéo
                         * est réellement générée par IA.
                         */
                        'is_aigc' => false,

                        /*
                         * À true si la vidéo fait la promotion
                         * de la propre entreprise du créateur.
                         */
                        'brand_organic_toggle' => false,
                    ],

                    'source_info' => [
                        'source' =>
                            'FILE_UPLOAD',

                        'video_size' =>
                            $videoSize,

                        'chunk_size' =>
                            $chunkSize,

                        'total_chunk_count' =>
                            $totalChunks,
                    ],
                ]
            );

        if (!$response->successful()) {
            $this->handleTikTokError(
                $response,
                $compte,
                'TikTok a refusé l’initialisation de la publication.'
            );
        }

        $publishId =
            $response->json('data.publish_id');

        $uploadUrl =
            $response->json('data.upload_url');

        if (!$publishId || !$uploadUrl) {
            throw new RuntimeException(
                'TikTok n’a pas retourné les informations nécessaires pour envoyer la vidéo.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Upload de la vidéo
        |--------------------------------------------------------------------------
        */

        $this->uploadVideo(
            $uploadUrl,
            $video['path'],
            $videoSize,
            $chunkSize,
            $mimeType
        );

        /*
        |--------------------------------------------------------------------------
        | Log
        |--------------------------------------------------------------------------
        */

        \Log::info(
            'TikTok publication initialisée',
            [
                'publication_id' =>
                    $publication->id,

                'project_id' =>
                    $publication->project_id,

                'publish_id' =>
                    $publishId,
            ]
        );

        return (string) $publishId;
    }

    /**
     * Trouve la première vidéo associée à la publication.
     */
    private function resolveVideo(
        Publication $publication
    ): ?array {
        $media = $publication->medias
            ->first(function ($media) {
                return
                    $media->type === 'video'
                    || str_starts_with(
                        strtolower(
                            $media->mime_type ?? ''
                        ),
                        'video/'
                    );
            });

        if (!$media || !$media->path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($media->path)) {
            throw new RuntimeException(
                'Le fichier vidéo sélectionné est introuvable dans le stockage.'
            );
        }

        return [
            'path' =>
                $media->path,

            'mime_type' =>
                $media->mime_type
                ?: 'video/mp4',

            'size' =>
                $disk->size($media->path),

            'name' =>
                $media->original_name
                ?: basename($media->path),
        ];
    }

    /**
     * Upload de la vidéo vers TikTok.
     */
    private function uploadVideo(
        string $uploadUrl,
        string $path,
        int $videoSize,
        int $chunkSize,
        string $mimeType
    ): void {
        $disk = Storage::disk('public');

        $filePath = $disk->path($path);

        if (!is_file($filePath)) {
            throw new RuntimeException(
                'Le fichier vidéo est introuvable sur le serveur.'
            );
        }

        $handle = fopen(
            $filePath,
            'rb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Impossible d’ouvrir la vidéo.'
            );
        }

        try {
            $start = 0;

            while ($start < $videoSize) {
                $remaining =
                    $videoSize - $start;

                $currentChunkSize =
                    min(
                        $chunkSize,
                        $remaining
                    );

                $contents = fread(
                    $handle,
                    $currentChunkSize
                );

                if ($contents === false) {
                    throw new RuntimeException(
                        'Impossible de lire la vidéo.'
                    );
                }

                $actualSize =
                    strlen($contents);

                if ($actualSize === 0) {
                    throw new RuntimeException(
                        'Lecture de la vidéo interrompue.'
                    );
                }

                $end =
                    $start
                    + $actualSize
                    - 1;

                $response = Http::withHeaders([
                    'Content-Type' =>
                        $mimeType,

                    'Content-Length' =>
                        (string) $actualSize,

                    'Content-Range' =>
                        "bytes {$start}-{$end}/{$videoSize}",
                ])
                    ->withBody(
                        $contents,
                        $mimeType
                    )
                    ->put($uploadUrl);

                if (!$response->successful()) {
                    \Log::error(
                        'TikTok video upload error',
                        [
                            'status' =>
                                $response->status(),

                            'response' =>
                                $response->json(),
                        ]
                    );

                    throw new RuntimeException(
                        'TikTok a refusé l’envoi de la vidéo.'
                    );
                }

                $start += $actualSize;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Construit la légende TikTok.
     */
    private function buildCaption(
        Publication $publication
    ): string {
        $title = trim(
            (string) $publication->title
        );

        $content = trim(
            (string) $publication->content
        );

        if ($title && $content) {
            $caption =
                $title
                . "\n\n"
                . $content;
        } elseif ($content) {
            $caption = $content;
        } else {
            $caption = $title;
        }

        return mb_substr(
            $caption,
            0,
            2200
        );
    }

    /**
     * Essaie de récupérer la durée de la vidéo avec ffprobe.
     */
    private function getVideoDuration(
        string $path
    ): ?int {
        $disk = Storage::disk('public');

        $filePath =
            $disk->path($path);

        if (!is_file($filePath)) {
            return null;
        }

        $command =
            'ffprobe -v error '
            . '-show_entries format=duration '
            . '-of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($filePath)
            . ' 2>/dev/null';

        $duration = shell_exec(
            $command
        );

        if ($duration === null) {
            return null;
        }

        $duration = trim($duration);

        if ($duration === '') {
            return null;
        }

        return (int) ceil(
            (float) $duration
        );
    }

    /**
     * Gère les erreurs TikTok.
     */
    private function handleTikTokError(
        $response,
        CompteSocial $compte,
        string $message
    ): void {
        $data = $response->json();

        \Log::error(
            'TikTok API error',
            [
                'status' =>
                    $response->status(),

                'response' =>
                    $data,
            ]
        );

        $errorCode =
            $data['error']['code']
            ?? null;

        if (
            $response->status() === 401
            || $errorCode === 'access_token_invalid'
        ) {
            $compte->update([
                'statut' => 'expire',
            ]);
        }

        $errorMessage =
            $data['error']['message']
            ?? null;

        throw new RuntimeException(
            $message
            . (
                $errorMessage
                    ? ' ' . $errorMessage
                    : ''
            )
        );
    }
}