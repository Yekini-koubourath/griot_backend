<?php

namespace App\Http\Controllers;

use App\Models\SocialAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Retourne les statistiques Analytics de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Période
        |--------------------------------------------------------------------------
        |
        | 7    = 7 jours
        | 30   = 30 jours
        | 90   = 3 mois
        | 365  = 12 mois
        |
        */

        $period = (int) $request->query('period', 30);

        if (!in_array($period, [7, 30, 90, 365], true)) {
            $period = 30;
        }

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($period - 1);

        /*
        |--------------------------------------------------------------------------
        | Requête principale
        |--------------------------------------------------------------------------
        */

        $analyticsQuery = SocialAnalytics::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Vue d'ensemble
        |--------------------------------------------------------------------------
        */

        $reach = (int) $analyticsQuery->sum('reach');

        $impressions = (int) $analyticsQuery->sum('impressions');

        $likes = (int) $analyticsQuery->sum('likes');

        $comments = (int) $analyticsQuery->sum('comments');

        $shares = (int) $analyticsQuery->sum('shares');

        $clicks = (int) $analyticsQuery->sum('clicks');

        $engagements = $likes + $comments + $shares;

        $engagementRate = $impressions > 0
            ? round(($engagements / $impressions) * 100, 2)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Statistiques par réseau
        |--------------------------------------------------------------------------
        */

        $networkStats = (clone $analyticsQuery)
            ->selectRaw('
                network,
                SUM(reach) as reach,
                SUM(impressions) as impressions,
                SUM(likes) as likes,
                SUM(comments) as comments,
                SUM(shares) as shares,
                SUM(clicks) as clicks
            ')
            ->groupBy('network')
            ->orderByDesc('reach')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Évolution quotidienne
        |--------------------------------------------------------------------------
        */

        $dailyStats = (clone $analyticsQuery)
            ->selectRaw('
                date,
                SUM(reach) as reach,
                SUM(impressions) as impressions,
                SUM(likes) as likes,
                SUM(comments) as comments,
                SUM(shares) as shares,
                SUM(clicks) as clicks
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Statistiques par publication
        |--------------------------------------------------------------------------
        */

        $publicationStats = (clone $analyticsQuery)
            ->whereNotNull('publication_id')
            ->selectRaw('
                publication_id,
                SUM(reach) as reach,
                SUM(impressions) as impressions,
                SUM(likes) as likes,
                SUM(comments) as comments,
                SUM(shares) as shares,
                SUM(clicks) as clicks
            ')
            ->groupBy('publication_id')
            ->orderByDesc('reach')
            ->with('publication:id,title,content,network,status')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Statistiques générales des publications
        |--------------------------------------------------------------------------
        */

        $publicationQuery = $user->publications()
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $publicationCount = $publicationQuery->count();

        $publishedCount = (clone $publicationQuery)
            ->where('status', 'Publiée')
            ->count();

        $scheduledCount = (clone $publicationQuery)
            ->where('status', 'Programmée')
            ->count();

        $draftCount = (clone $publicationQuery)
            ->where('status', 'Brouillon')
            ->count();

        $failedCount = (clone $publicationQuery)
            ->where('status', 'Échec')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Répartition des publications par réseau
        |--------------------------------------------------------------------------
        */

        $publicationsByNetwork = $publicationQuery
            ->selectRaw('network, COUNT(*) as total')
            ->groupBy('network')
            ->orderByDesc('total')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Campagnes
        |--------------------------------------------------------------------------
        */

        $campaignCount = $user->campaigns()
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->whereBetween('start_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->orWhereBetween('end_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query
                            ->whereNull('start_date')
                            ->whereNull('end_date');
                    });
            })
            ->count();

        $activeCampaignCount = $user->campaigns()
            ->where('status', 'active')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Meilleures publications
        |--------------------------------------------------------------------------
        */

        $topPosts = $publicationStats
            ->take(5)
            ->map(function ($stat) {
                return [
                    'id' => $stat->publication_id,
                    'title' => $stat->publication?->title,
                    'network' => $stat->publication?->network,
                    'status' => $stat->publication?->status,
                    'reach' => (int) $stat->reach,
                    'impressions' => (int) $stat->impressions,
                    'likes' => (int) $stat->likes,
                    'comments' => (int) $stat->comments,
                    'shares' => (int) $stat->shares,
                    'clicks' => (int) $stat->clicks,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Répartition de l'engagement
        |--------------------------------------------------------------------------
        */

        $engagementDistribution = [
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
        ];

        /*
        |--------------------------------------------------------------------------
        | Réponse API
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'period' => [
                'days' => $period,
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],

            'overview' => [
                'reach' => $reach,
                'impressions' => $impressions,
                'engagement' => $engagementRate,
                'clicks' => $clicks,
            ],

            'metrics' => [
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'clicks' => $clicks,
                'engagements' => $engagements,
            ],

            'publications' => [
                'total' => $publicationCount,
                'published' => $publishedCount,
                'scheduled' => $scheduledCount,
                'draft' => $draftCount,
                'failed' => $failedCount,
            ],

            'campaigns' => [
                'total' => $campaignCount,
                'active' => $activeCampaignCount,
            ],

            'networks' => $networkStats
                ->map(function ($network) {
                    return [
                        'network' => $network->network,
                        'reach' => (int) $network->reach,
                        'impressions' => (int) $network->impressions,
                        'likes' => (int) $network->likes,
                        'comments' => (int) $network->comments,
                        'shares' => (int) $network->shares,
                        'clicks' => (int) $network->clicks,
                    ];
                })
                ->values(),

            'daily' => $dailyStats
                ->map(function ($day) {
                    return [
                        'date' => $day->date,
                        'reach' => (int) $day->reach,
                        'impressions' => (int) $day->impressions,
                        'likes' => (int) $day->likes,
                        'comments' => (int) $day->comments,
                        'shares' => (int) $day->shares,
                        'clicks' => (int) $day->clicks,
                    ];
                })
                ->values(),

            'publications_by_network' => $publicationsByNetwork
                ->map(function ($network) {
                    return [
                        'network' => $network->network,
                        'total' => (int) $network->total,
                    ];
                })
                ->values(),

            'top_posts' => $topPosts,

            'engagement_distribution' => $engagementDistribution,

            'insight' => [
                'has_data' => $reach > 0 || $impressions > 0,
                'message' => $this->generateInsight(
                    $reach,
                    $impressions,
                    $engagementRate,
                    $networkStats
                ),
            ],
        ]);
    }

    /**
     * Génère une petite synthèse pour le dashboard.
     */
    private function generateInsight(
        int $reach,
        int $impressions,
        float $engagementRate,
        $networkStats
    ): string {
        if ($reach === 0 && $impressions === 0) {
            return 'Aucune donnée Analytics disponible pour cette période.';
        }

        if ($networkStats->isNotEmpty()) {
            $bestNetwork = $networkStats->first()->network;

            return "Le réseau {$bestNetwork} génère actuellement votre meilleure portée sur la période sélectionnée.";
        }

        if ($engagementRate > 5) {
            return 'Votre taux d’engagement est supérieur à 5 % sur la période sélectionnée.';
        }

        return 'Continuez à publier régulièrement pour enrichir vos données Analytics.';
    }
}