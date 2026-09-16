<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;

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
            ->with('folder')
            ->latest()
            ->get();

        /*
         * On recalcule le type à partir du vrai MIME.
         */
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
        /*
         * On vérifie uniquement que c'est bien un fichier
         * et qu'il ne dépasse pas 50 Mo.
         *
         * On ne bloque plus l'import avec "mimes",
         * car certains navigateurs/environnements peuvent
         * envoyer un MIME différent de l'extension réelle.
         */
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

        /*
         * MIME réel du fichier.
         */
        $mimeType = strtolower(
            (string) $file->getMimeType()
        );

        /*
         * Détection du type du média.
         */
        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } else {
            $type = 'document';
        }

        /*
         * Stockage :
         * storage/app/public/medias
         */
        $path = $file->store(
            'medias',
            'public'
        );

        /*
         * Création du média.
         */
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

        /*
         * Charger le dossier associé.
         */
        $media->load('folder');

        return response()->json([
            'message' =>
                'Média importé avec succès.',

            'media' => $media,
        ], 201);
    }
}