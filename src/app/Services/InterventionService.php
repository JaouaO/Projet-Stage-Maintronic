<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InterventionService
{
    public function list(int $limit = 300): array
    {
        // 1) Dataset principal (Query Builder)
        $rows = DB::table('t_intervention as i')
            ->selectRaw("
                i.NumInt                                         as num_int,
                COALESCE(NULLIF(i.NomLivCli,''), i.NomFactCli, '(client inconnu)') as client,
                NULLIF(i.DateIntPrevu,'0000-00-00')             as date_prev,
                NULLIF(i.HeureIntPrevu,'00:00:00')              as heure_prev,
                NULLIF(i.DateDebInterv,'0000-00-00')            as date_deb,
                NULLIF(i.DateFinInterv,'0000-00-00')            as date_fin,
                i.StatutInterv                                   as statut,
                i.CodeTech                                       as code_tech,
                CASE
                  WHEN NULLIF(i.DateFinInterv,'0000-00-00') IS NOT NULL THEN 'CLOTURER'
                  WHEN NULLIF(i.DateIntPrevu,'0000-00-00') IS NULL THEN 'PLANIFIER_RDV'
                  WHEN NULLIF(i.DateIntPrevu,'0000-00-00') IS NOT NULL
                       AND NULLIF(i.DateDebInterv,'0000-00-00') IS NULL THEN 'CONFIRMER_RDV'
                  ELSE 'DIAGNOSTIC'
                END                                              as todo
            ")
            ->orderByRaw("
                COALESCE(NULLIF(i.DateIntPrevu,'0000-00-00'), '9999-12-31') ASC,
                NULLIF(i.HeureIntPrevu,'00:00:00') ASC,
                i.NumInt ASC
            ")
            ->limit($limit)
            ->get();

        // 2) Compteurs par TODO (fromSub + groupBy)
        $todoSub = DB::table('t_intervention')
            ->selectRaw("
                CASE
                  WHEN NULLIF(DateFinInterv,'0000-00-00') IS NOT NULL THEN 'CLOTURER'
                  WHEN NULLIF(DateIntPrevu,'0000-00-00') IS NULL THEN 'PLANIFIER_RDV'
                  WHEN NULLIF(DateIntPrevu,'0000-00-00') IS NOT NULL
                       AND NULLIF(DateDebInterv,'0000-00-00') IS NULL THEN 'CONFIRMER_RDV'
                  ELSE 'DIAGNOSTIC'
                END as todo
            ");

        $counts = DB::query()
            ->fromSub($todoSub, 'x')
            ->select('todo', DB::raw('COUNT(*) as nb'))
            ->groupBy('todo')
            ->get()
            ->keyBy('todo');

        // 3) “Prochain appel” (plus proche date+heure valides)
        [$nextNumInt, $nextIndex] = $this->computeNext($rows);

        return [
            'rows'       => $rows,
            'counts'     => $counts,
            'nextIndex'  => $nextIndex,   // index dans la collec (pratique pour la classe .next)
            'nextNumInt' => $nextNumInt,  // au cas où tu veux l’exploiter ailleurs
            'total'      => $rows->count(),
        ];
    }

    private function computeNext(Collection $rows): array
    {
        $bestIdx = null;
        $bestTs  = null;

        foreach ($rows as $idx => $r) {
            if (empty($r->date_prev) || empty($r->heure_prev)) {
                continue;
            }
            try {
                $ts = Carbon::parse($r->date_prev.' '.$r->heure_prev);
            } catch (\Throwable $e) {
                continue;
            }
            if (is_null($bestTs) || $ts->lt($bestTs)) {
                $bestTs  = $ts;
                $bestIdx = $idx;
            }
        }

        $num = $bestIdx !== null ? ($rows[$bestIdx]->num_int ?? null) : null;
        return [$num, $bestIdx];
    }
    public function listPaginatedSimple($perPage = 25)
    {
        $perPage = (int) $perPage ?: 25;

        return DB::table('t_intervention as i')
            ->selectRaw("
                i.NumInt AS num_int,
                COALESCE(NULLIF(i.NomLivCli,''), i.NomFactCli, '(client inconnu)') AS client,
                NULLIF(i.DateIntPrevu,'0000-00-00') AS date_prev,
                NULLIF(i.HeureIntPrevu,'00:00:00')  AS heure_prev,
                NULLIF(i.DateDebInterv,'0000-00-00') AS date_deb,
                NULLIF(i.DateFinInterv,'0000-00-00') AS date_fin,
                i.StatutInterv AS statut,
                i.CodeTech AS code_tech,
                CASE
                  WHEN NULLIF(i.DateFinInterv,'0000-00-00') IS NOT NULL THEN 'CLOTURER'
                  WHEN NULLIF(i.DateIntPrevu,'0000-00-00') IS NULL THEN 'PLANIFIER_RDV'
                  WHEN NULLIF(i.DateIntPrevu,'0000-00-00') IS NOT NULL
                       AND NULLIF(i.DateDebInterv,'0000-00-00') IS NULL THEN 'CONFIRMER_RDV'
                  ELSE 'DIAGNOSTIC'
                END AS todo
            ")
            // ordre initial (cohérent pour lecture), mais le tri final sera JS
            ->orderByRaw("
                COALESCE(NULLIF(i.DateIntPrevu,'0000-00-00'), '9999-12-31') ASC,
                COALESCE(NULLIF(i.HeureIntPrevu,'00:00:00'), '23:59:59') ASC,
                i.NumInt ASC
            ")
            ->paginate($perPage);
    }
}
