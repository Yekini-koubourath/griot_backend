<?php

namespace App\Http\Controllers;

use App\Models\CompteSocial;
use App\Models\Project;
use Illuminate\Http\Request;

class CompteSocialController extends Controller
{
    public function index(Request $request, Project $project)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $comptes = $project->comptesSociaux()
            ->select(['id', 'reseau', 'compte_id', 'nom_affichage', 'nom_utilisateur', 'avatar_url', 'statut', 'token_expires_at', 'created_at'])
            ->get();

        return response()->json(['comptes' => $comptes]);
    }

    public function destroy(Request $request, Project $project, CompteSocial $compte)
    {
        if ($project->user_id !== $request->user()->id || $compte->project_id !== $project->id) {
            abort(403);
        }

        $compte->delete();

        return response()->json(['message' => 'Compte déconnecté.']);
    }
}