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
            ->with('project')
            ->latest()
            ->get();

        return response()->json($publications);
    }

    /**
     * Crée une ou plusieurs publications.
     *
     * Une publication est créée pour chaque réseau sélectionné.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'networks' => ['required', 'array', 'min:1'],
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
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'image' => ['nullable', 'string'],
        ]);

        // Vérifie que le projet appartient bien à l'utilisateur connecté.
        if (!empty($validated['project_id'])) {
            $projectExists = $request->user()
                ->projects()
                ->where('id', $validated['project_id'])
                ->exists();

            if (!$projectExists) {
                return response()->json([
                    'message' => 'Ce projet ne vous appartient pas.',
                ], 403);
            }
        }

        $publications = [];

        foreach ($validated['networks'] as $network) {
            $publications[] = $request->user()
                ->publications()
                ->create([
                    'project_id' => $validated['project_id'] ?? null,
                    'title' => $validated['title'],
                    'content' => $validated['content'],
                    'network' => $network,
                    'status' => $validated['status'],
                    'date' => $validated['date'] ?? null,
                    'time' => $validated['time'] ?? null,
                    'image' => $validated['image'] ?? null,
                ]);
        }

        return response()->json([
            'message' => 'Publication(s) créée(s) avec succès.',
            'publications' => $publications,
        ], 201);
    }

    /**
     * Affiche une publication appartenant à l'utilisateur connecté.
     */
    public function show(Request $request, Publication $publication)
    {
        if ($publication->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Cette publication ne vous appartient pas.',
            ], 403);
        }

        return response()->json(
            $publication->load('project')
        );
    }

    /**
     * Modifie une publication.
     */
    public function update(Request $request, Publication $publication)
    {
        if ($publication->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Cette publication ne vous appartient pas.',
            ], 403);
        }

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
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
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'image' => ['nullable', 'string'],
        ]);

        if (!empty($validated['project_id'])) {
            $projectExists = $request->user()
                ->projects()
                ->where('id', $validated['project_id'])
                ->exists();

            if (!$projectExists) {
                return response()->json([
                    'message' => 'Ce projet ne vous appartient pas.',
                ], 403);
            }
        }

        $publication->update([
            'project_id' => $validated['project_id'] ?? null,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'network' => $validated['network'],
            'status' => $validated['status'],
            'date' => $validated['date'] ?? null,
            'time' => $validated['time'] ?? null,
            'image' => $validated['image'] ?? null,
        ]);

        return response()->json([
            'message' => 'Publication modifiée avec succès.',
            'publication' => $publication->load('project'),
        ]);
    }

    /**
     * Supprime une publication.
     */
    public function destroy(Request $request, Publication $publication)
    {
        if ($publication->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Cette publication ne vous appartient pas.',
            ], 403);
        }

        $publication->delete();

        return response()->json([
            'message' => 'Publication supprimée avec succès.',
        ]);
    }
}