<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProjectController extends Controller
{
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

    public function store(Request $request)
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'status' => ['required', 'in:Actif,En pause,Archivé'],
    ]);

    $project = $request->user()
        ->projects()
        ->create($validated);

    return response()->json([
        'message' => 'Projet créé avec succès',
        'project' => $project,
    ], 201);
}
}