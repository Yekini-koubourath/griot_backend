<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    /**
     * Liste uniquement les projets de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $projects = $request->user()
            ->projects()
            ->latest()
            ->get();

        return response()->json([
            'projects' => $projects,
        ]);
    }

    /**
     * Affiche un projet précis.
     */
    public function show(Request $request, Project $project)
    {
        // Sécurité : le projet doit appartenir à l'utilisateur connecté.
        if ($project->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à consulter ce projet.',
            ], 403);
        }

        return response()->json([
            'project' => $project,
        ]);
    }

    /**
     * Crée un nouveau projet.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:Actif,En pause,Archivé'],
            'members' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $validated['members'] = (int) ($validated['members'] ?? 1);

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/' .
                $request->file('image')->store('projects', 'public');
        }

        $project = $request->user()
            ->projects()
            ->create($validated);

        return response()->json([
            'message' => 'Projet créé avec succès.',
            'project' => $project,
        ], 201);
    }

    /**
     * Modifie un projet existant.
     */
    public function update(Request $request, Project $project)
    {
        // Sécurité : le projet doit appartenir à l'utilisateur connecté.
        if ($project->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à modifier ce projet.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:Actif,En pause,Archivé'],
            'members' => ['sometimes', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        /*
         * Si une nouvelle image est envoyée,
         * on supprime l'ancienne image avant de la remplacer.
         */
        if ($request->hasFile('image')) {
            if ($project->image) {
                $oldImage = str_replace('/storage/', '', $project->image);

                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            $validated['image'] = '/storage/' .
                $request->file('image')->store('projects', 'public');
        } else {
            // On conserve l'image existante.
            unset($validated['image']);
        }

        $project->update($validated);

        return response()->json([
            'message' => 'Projet modifié avec succès.',
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Archive un projet.
     */
    public function archive(Request $request, Project $project)
    {
        // Sécurité : le projet doit appartenir à l'utilisateur connecté.
        if ($project->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à archiver ce projet.',
            ], 403);
        }

        if ($project->status === 'Archivé') {
            return response()->json([
                'message' => 'Ce projet est déjà archivé.',
            ], 422);
        }

        $project->update([
            'status' => 'Archivé',
        ]);

        return response()->json([
            'message' => 'Projet archivé avec succès.',
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Désarchive un projet.
     */
    public function restore(Request $request, Project $project)
    {
        // Sécurité : le projet doit appartenir à l'utilisateur connecté.
        if ($project->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à restaurer ce projet.',
            ], 403);
        }

        if ($project->status !== 'Archivé') {
            return response()->json([
                'message' => 'Ce projet n\'est pas archivé.',
            ], 422);
        }

        $project->update([
            'status' => 'Actif',
        ]);

        return response()->json([
            'message' => 'Projet restauré avec succès.',
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Supprime définitivement un projet.
     */
    public function destroy(Request $request, Project $project)
    {
        // Sécurité : le projet doit appartenir à l'utilisateur connecté.
        if ($project->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce projet.',
            ], 403);
        }

        /*
         * Suppression de l'image associée au projet.
         */
        if ($project->image) {
            $image = str_replace('/storage/', '', $project->image);

            if (Storage::disk('public')->exists($image)) {
                Storage::disk('public')->delete($image);
            }
        }

        $project->delete();

        return response()->json([
            'message' => 'Projet supprimé avec succès.',
        ]);
    }
}