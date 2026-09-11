<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with(['souscriptions' => function ($q) {
            $q->latest()->limit(1)->with('plan:id,nom');
        }]);

        if ($request->filled('recherche')) {
            $terme = $request->query('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('name', 'like', "%{$terme}%")
                    ->orWhere('email', 'like', "%{$terme}%");
            });
        }

        return response()->json([
            'users' => $query->latest()->paginate(20),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'in:user,admin'],
        ]);

        $user->update($validated);

        return response()->json(['message' => 'Utilisateur mis à jour', 'user' => $user]);
    }
}