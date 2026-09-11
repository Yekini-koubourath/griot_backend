<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Souscription;
use Illuminate\Http\Request;

class AdminSouscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Souscription::with(['user:id,name,email', 'plan:id,nom']);

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        return response()->json([
            'souscriptions' => $query->latest()->paginate(20),
        ]);
    }

    public function valider(Souscription $souscription)
    {
        $souscription->update(['statut' => 'actif']);

        return response()->json([
            'message' => 'Souscription validée, accès activé.',
            'souscription' => $souscription->load(['user:id,name,email', 'plan:id,nom']),
        ]);
    }

    public function rejeter(Request $request, Souscription $souscription)
    {
        $validated = $request->validate([
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $details = $souscription->details_paiement ?? [];
        $details['motif_rejet'] = $validated['motif'] ?? 'Paiement non confirmé';

        $souscription->update([
            'statut' => 'expiree',
            'details_paiement' => $details,
        ]);

        return response()->json([
            'message' => 'Souscription rejetée.',
            'souscription' => $souscription->load(['user:id,name,email', 'plan:id,nom']),
        ]);
    }
}