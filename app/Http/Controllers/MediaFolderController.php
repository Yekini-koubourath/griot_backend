<?php

namespace App\Http\Controllers;

use App\Models\MediaFolder;
use Illuminate\Http\Request;

class MediaFolderController extends Controller
{
    /**
     * Récupérer les dossiers de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $folders = MediaFolder::where('user_id', $request->user()->id)
            ->withCount('medias')
            ->latest()
            ->get();

        return response()->json([
            'folders' => $folders,
        ]);
    }

    /**
     * Créer un dossier.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $folder = MediaFolder::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
        ]);

        $folder->loadCount('medias');

        return response()->json([
            'message' => 'Dossier créé avec succès.',
            'folder' => $folder,
        ], 201);
    }
}