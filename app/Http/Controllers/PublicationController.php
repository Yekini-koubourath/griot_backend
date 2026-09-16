<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    /**
     * Liste les publications de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $publications = $request->user()
            ->publications()
            ->with([
                'project',
                'medias',
            ])
            ->latest()
            ->get();

        return response()->json($publications);
    }

    /**
     * Crée une ou plusieurs publications.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => [
                'nullable',
                'integer',
                'exists:projects,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'content' => [
                'required',
                'string',
            ],

            'networks' => [
                'required',
                'array',
                'min:1',
            ],

            'networks.*' => [
                'required',
                'string',
                'in:Facebook,Instagram,LinkedIn,TikTok,Google Business,X',
            ],

            'status' => [
                'required',
                'string',
                'in:Publiée,Programmée,Brouillon,Échec',
            ],

            'date' => [
                'nullable',
                'date',
            ],

            'time' => [
                'nullable',
                'date_format:H:i',
            ],

            'image' => [
                'nullable',
                'string',
            ],

            'media_ids' => [
                'nullable',
                'array',
            ],

            'media_ids.*' => [
                'integer',
                'exists:media,id',
            ],
        ]);

        /*
         * Vérification du projet.
         */
        if (!empty($validated['project_id'])) {
            $projectExists = $request
                ->user()
                ->projects()
                ->where(
                    'id',
                    $validated['project_id']
                )
                ->exists();

            if (!$projectExists) {
                return response()->json([
                    'message' =>
                        'Ce projet ne vous appartient pas.',
                ], 403);
            }
        }

        /*
         * Vérification des médias.
         *
         * Tous les médias doivent appartenir
         * à l'utilisateur connecté.
         */
        $mediaIds = $validated['media_ids'] ?? [];

        if (!empty($mediaIds)) {
            $validMediaCount = $request
                ->user()
                ->medias()
                ->whereIn(
                    'id',
                    $mediaIds
                )
                ->count();

            if (
                $validMediaCount !==
                count(array_unique($mediaIds))
            ) {
                return response()->json([
                    'message' =>
                        'Un ou plusieurs médias ne vous appartiennent pas.',
                ], 403);
            }
        }

        $publications = [];

        foreach ($validated['networks'] as $network) {
            $publication = $request
                ->user()
                ->publications()
                ->create([
                    'project_id' =>
                        $validated['project_id']
                        ?? null,

                    'title' =>
                        $validated['title'],

                    'content' =>
                        $validated['content'],

                    'network' =>
                        $network,

                    'status' =>
                        $validated['status'],

                    'date' =>
                        $validated['date']
                        ?? null,

                    'time' =>
                        $validated['time']
                        ?? null,

                    'image' =>
                        $validated['image']
                        ?? null,
                ]);

            if (!empty($mediaIds)) {
                $publication
                    ->medias()
                    ->sync($mediaIds);
            }

            $publications[] = $publication
                ->load([
                    'project',
                    'medias',
                ]);
        }

        return response()->json([
            'message' =>
                'Publication(s) créée(s) avec succès.',

            'publications' =>
                $publications,
        ], 201);
    }

    /**
     * Affiche une publication.
     */
    public function show(
        Request $request,
        Publication $publication
    ) {
        if (
            $publication->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Cette publication ne vous appartient pas.',
            ], 403);
        }

        return response()->json(
            $publication->load([
                'project',
                'medias',
            ])
        );
    }

    /**
     * Modifie une publication.
     */
    public function update(
        Request $request,
        Publication $publication
    ) {
        if (
            $publication->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Cette publication ne vous appartient pas.',
            ], 403);
        }

        $validated = $request->validate([
            'project_id' => [
                'nullable',
                'integer',
                'exists:projects,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'content' => [
                'required',
                'string',
            ],

            'network' => [
                'required',
                'string',
                'in:Facebook,Instagram,LinkedIn,TikTok,Google Business,X',
            ],

            'status' => [
                'required',
                'string',
                'in:Publiée,Programmée,Brouillon,Échec',
            ],

            'date' => [
                'nullable',
                'date',
            ],

            'time' => [
                'nullable',
                'date_format:H:i',
            ],

            'image' => [
                'nullable',
                'string',
            ],

            'media_ids' => [
                'nullable',
                'array',
            ],

            'media_ids.*' => [
                'integer',
                'exists:media,id',
            ],
        ]);

        if (!empty($validated['project_id'])) {
            $projectExists = $request
                ->user()
                ->projects()
                ->where(
                    'id',
                    $validated['project_id']
                )
                ->exists();

            if (!$projectExists) {
                return response()->json([
                    'message' =>
                        'Ce projet ne vous appartient pas.',
                ], 403);
            }
        }

        $mediaIds = $validated['media_ids'] ?? [];

        if (!empty($mediaIds)) {
            $validMediaCount = $request
                ->user()
                ->medias()
                ->whereIn(
                    'id',
                    $mediaIds
                )
                ->count();

            if (
                $validMediaCount !==
                count(array_unique($mediaIds))
            ) {
                return response()->json([
                    'message' =>
                        'Un ou plusieurs médias ne vous appartiennent pas.',
                ], 403);
            }
        }

        $publication->update([
            'project_id' =>
                $validated['project_id']
                ?? null,

            'title' =>
                $validated['title'],

            'content' =>
                $validated['content'],

            'network' =>
                $validated['network'],

            'status' =>
                $validated['status'],

            'date' =>
                $validated['date']
                ?? null,

            'time' =>
                $validated['time']
                ?? null,

            'image' =>
                $validated['image']
                ?? null,
        ]);

        /*
         * sync([]) permet également de retirer
         * les anciens médias.
         */
        $publication
            ->medias()
            ->sync($mediaIds);

        return response()->json([
            'message' =>
                'Publication modifiée avec succès.',

            'publication' =>
                $publication->load([
                    'project',
                    'medias',
                ]),
        ]);
    }

    /**
     * Supprime une publication.
     */
    public function destroy(
        Request $request,
        Publication $publication
    ) {
        if (
            $publication->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Cette publication ne vous appartient pas.',
            ], 403);
        }

        $publication->delete();

        return response()->json([
            'message' =>
                'Publication supprimée avec succès.',
        ]);
    }
}