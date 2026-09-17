<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    /**
     * Liste les campagnes de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $campaigns = $request->user()
            ->campaigns()
            ->with([
                'project',
                'publications',
            ])
            ->latest()
            ->get()
            ->map(function ($campaign) {
                return $this->formatCampaign($campaign);
            });

        return response()->json($campaigns);
    }

    /**
     * Crée une nouvelle campagne.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => [
                'nullable',
                'integer',
                'exists:projects,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'nullable',
                'in:active,scheduled,completed,paused',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $user = $request->user();

        /*
         * Si un projet est fourni,
         * on vérifie qu'il appartient bien à l'utilisateur.
         */
        if (!empty($validated['project_id'])) {
            $projectExists = $user->projects()
                ->where('id', $validated['project_id'])
                ->exists();

            if (!$projectExists) {
                return response()->json([
                    'message' => 'Ce projet ne vous appartient pas.',
                ], 403);
            }
        }

        $campaign = $user->campaigns()->create([
            'project_id' => $validated['project_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'scheduled',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        $campaign->load([
            'project',
            'publications',
        ]);

        return response()->json([
            'message' => 'Campagne créée avec succès.',
            'campaign' => $this->formatCampaign($campaign),
        ], 201);
    }

    /**
     * Affiche une campagne précise.
     */
    public function show(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->load([
            'project',
            'publications.medias',
        ]);

        return response()->json(
            $this->formatCampaign($campaign)
        );
    }

    /**
     * Modifie une campagne.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($request, $campaign);

        $validated = $request->validate([
            'project_id' => [
                'nullable',
                'integer',
                'exists:projects,id',
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'nullable',
                'in:active,scheduled,completed,paused',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $user = $request->user();

        /*
         * Si le projet est modifié,
         * on vérifie qu'il appartient bien à l'utilisateur.
         */
        if (
            array_key_exists('project_id', $validated)
            && !empty($validated['project_id'])
        ) {
            $projectExists = $user->projects()
                ->where('id', $validated['project_id'])
                ->exists();

            if (!$projectExists) {
                return response()->json([
                    'message' => 'Ce projet ne vous appartient pas.',
                ], 403);
            }
        }

        $campaign->update($validated);

        $campaign->load([
            'project',
            'publications',
        ]);

        return response()->json([
            'message' => 'Campagne modifiée avec succès.',
            'campaign' => $this->formatCampaign($campaign),
        ]);
    }

    /**
     * Supprime une campagne.
     *
     * Les publications ne sont pas supprimées.
     * Leur campaign_id devient NULL grâce à la relation
     * définie dans la migration.
     */
    public function destroy(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->delete();

        return response()->json([
            'message' => 'Campagne supprimée avec succès.',
        ]);
    }

    /**
     * Duplique une campagne avec ses informations principales.
     *
     * Les publications ne sont PAS dupliquées.
     */
    public function duplicate(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($request, $campaign);

        $newCampaign = DB::transaction(function () use ($campaign) {
            $newCampaign = $campaign->replicate();

            $newCampaign->name = $campaign->name . ' - Copie';
            $newCampaign->status = 'scheduled';

            $newCampaign->save();

            return $newCampaign;
        });

        $newCampaign->load([
            'project',
            'publications',
        ]);

        return response()->json([
            'message' => 'Campagne dupliquée avec succès.',
            'campaign' => $this->formatCampaign($newCampaign),
        ], 201);
    }

    /**
     * Met une campagne en pause.
     */
    public function pause(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->update([
            'status' => 'paused',
        ]);

        $campaign->load([
            'project',
            'publications',
        ]);

        return response()->json([
            'message' => 'Campagne mise en pause.',
            'campaign' => $this->formatCampaign($campaign),
        ]);
    }

    /**
     * Reprend une campagne.
     */
    public function resume(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->update([
            'status' => 'active',
        ]);

        $campaign->load([
            'project',
            'publications',
        ]);

        return response()->json([
            'message' => 'Campagne reprise.',
            'campaign' => $this->formatCampaign($campaign),
        ]);
    }

    /**
     * Vérifie que la campagne appartient bien
     * à l'utilisateur connecté.
     */
    private function authorizeCampaign(
        Request $request,
        Campaign $campaign
    ): void {
        if ($campaign->user_id !== $request->user()->id) {
            abort(response()->json([
                'message' => 'Cette campagne ne vous appartient pas.',
            ], 403));
        }
    }

    /**
     * Formate une campagne pour le frontend.
     */
    private function formatCampaign(Campaign $campaign): array
    {
        $publications = $campaign->publications ?? collect();

        $totalPublications = $publications->count();

        $publishedPublications = $publications
            ->where('status', 'Publiée')
            ->count();

        $scheduledPublications = $publications
            ->where('status', 'Programmée')
            ->count();

        $draftPublications = $publications
            ->where('status', 'Brouillon')
            ->count();

        $failedPublications = $publications
            ->where('status', 'Échec')
            ->count();

        $progress = $totalPublications > 0
            ? round(
                ($publishedPublications / $totalPublications) * 100,
                1
            )
            : 0;

        $networks = $publications
            ->pluck('network')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return [
            'id' => $campaign->id,

            'name' => $campaign->name,

            'description' => $campaign->description,

            'status' => $campaign->status,

            'start_date' => $campaign->start_date?->format('Y-m-d'),

            'end_date' => $campaign->end_date?->format('Y-m-d'),

            'project' => $campaign->project
                ? [
                    'id' => $campaign->project->id,
                    'name' => $campaign->project->name,
                ]
                : null,

            'project_id' => $campaign->project_id,

            'publications' => [
                'total' => $totalPublications,
                'published' => $publishedPublications,
                'scheduled' => $scheduledPublications,
                'draft' => $draftPublications,
                'failed' => $failedPublications,
            ],

            'progress' => $progress,

            'networks' => $networks,

            /*
             * Ces valeurs pourront être alimentées
             * plus tard avec les vraies statistiques
             * des réseaux sociaux.
             */
            'reach' => 0,

            'engagement' => 0,

            'created_at' => $campaign->created_at?->toISOString(),

            'updated_at' => $campaign->updated_at?->toISOString(),
        ];
    }
}
