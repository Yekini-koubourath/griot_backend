<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Récupérer tous les médias de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $medias = Media::where(
            'user_id',
            $request->user()->id
        )
            ->with([
                'folder',
                'publications:id,title,network,status',
            ])
            ->latest()
            ->get();

        $medias->each(function ($media) {
            $mimeType = strtolower(
                (string) $media->mime_type
            );

            if (str_starts_with($mimeType, 'image/')) {
                $media->type = 'image';
            } elseif (str_starts_with($mimeType, 'video/')) {
                $media->type = 'video';
            } else {
                $media->type = 'document';
            }
        });

        return response()->json([
            'medias' => $medias,
        ]);
    }

    /**
     * Importer un média.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
            ],

            'folder_id' => [
                'nullable',
                'integer',

                function (
                    $attribute,
                    $value,
                    $fail
                ) use ($request) {
                    $exists = $request
                        ->user()
                        ->mediaFolders()
                        ->where(
                            'id',
                            $value
                        )
                        ->exists();

                    if (!$exists) {
                        $fail(
                            "Le dossier sélectionné n'appartient pas à votre compte."
                        );
                    }
                },
            ],
        ]);

        $file = $request->file('file');

        $mimeType = strtolower(
            (string) $file->getMimeType()
        );

        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } else {
            $type = 'document';
        }

        $path = $file->store(
            'medias',
            'public'
        );

        $media = Media::create([
            'user_id' => $request->user()->id,

            'folder_id' => $request->input('folder_id'),

            'name' => pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_FILENAME
            ),

            'original_name' =>
                $file->getClientOriginalName(),

            'type' => $type,

            'mime_type' => $mimeType,

            'size' => $file->getSize(),

            'path' => $path,
        ]);

        $media->load([
            'folder',
            'publications',
        ]);

        return response()->json([
            'message' =>
                'Média importé avec succès.',

            'media' => $media,
        ], 201);
    }

    /**
     * Télécharger un média.
     */
    public function download(
        Request $request,
        Media $media
    ) {
        if (
            $media->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Ce média ne vous appartient pas.',
            ], 403);
        }

        if (
            !Storage::disk('public')
                ->exists($media->path)
        ) {
            return response()->json([
                'message' =>
                    'Le fichier demandé est introuvable.',
            ], 404);
        }

        return Storage::disk('public')->download(
            $media->path,
            $media->original_name,
            [
                'Content-Type' =>
                    $media->mime_type,
            ]
        );
    }

    /**
     * Supprimer un média.
     */
    public function destroy(
        Request $request,
        Media $media
    ) {
        if (
            $media->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Ce média ne vous appartient pas.',
            ], 403);
        }

        /*
         * Supprime le fichier physique.
         */
        if (
            Storage::disk('public')
                ->exists($media->path)
        ) {
            Storage::disk('public')
                ->delete($media->path);
        }

        /*
         * Les relations dans media_publication
         * sont supprimées automatiquement grâce
         * à cascadeOnDelete().
         */
        $media->delete();

        return response()->json([
            'message' =>
                'Média supprimé avec succès.',
        ]);
    }
}