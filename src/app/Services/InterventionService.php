<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InterventionService
{
/**
* N'affiche que les interventions dont le NumInt commence par une agence autorisée.
* - client        = contact_reel (fallback "(contact inconnu)")
* - a_faire       = objet_traitement (fallback "À préciser")
* - date/heure    = COALESCE(tech_rdv_at, rdv_prev_at)
* - urgent        = t_actions_etat.urgent
* - concerne      = reaffecte_code==$codeSal OR tech_code==$codeSal
* - tri           = concerne DESC, urgent DESC, date/heure ASC, NumInt ASC
*/
// App\Services\InterventionService::listPaginatedSimple(...)

    // App\Services\InterventionService

    public function listPaginatedSimple(int $perPage = 25, array $agencesAutorisees = [], ?string $codeSal = null): LengthAwarePaginator
    {
        if (empty($agencesAutorisees)) {
            return new LengthAwarePaginator(collect(), 0, $perPage);
        }

        $query = DB::table('t_actions_etat as ae')
            ->leftJoin('t_actions_vocabulaire as v', function ($join) {
                $join->on('v.code', '=', 'ae.objet_traitement')   // <= si objet_traitement contient le CODE
                ->where('v.group_code', '=', 'AFFECTATION');

                // Si, chez vous, objet_traitement contient le LABEL et non le code,
                // remplacez la ligne de jointure par :
                // $join->on('v.label', '=', 'ae.objet_traitement')->where('v.group_code', '=', 'AFFECTATION');
            })
            ->selectRaw("
            ae.NumInt AS num_int,
            COALESCE(NULLIF(ae.contact_reel,''), '(contact inconnu)') AS client,
            DATE(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS date_prev,
            TIME(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS heure_prev,

            ae.tech_code,
            ae.reaffecte_code,
            ae.urgent,

            -- drapeaux
            CASE WHEN ae.reaffecte_code = ? OR ae.tech_code = ? THEN 1 ELSE 0 END AS concerne,

            -- À faire : code/label depuis vocab AFFECTATION
            v.code  AS a_faire_code,
            COALESCE(NULLIF(v.label,''), COALESCE(NULLIF(ae.objet_traitement,''), 'À préciser')) AS a_faire_label,

            -- Ordre de priorité
            CASE
              WHEN ae.urgent=1 AND (ae.reaffecte_code = ? OR ae.tech_code = ?) THEN 0
              WHEN ae.urgent=1 THEN 1
              WHEN (ae.reaffecte_code = ? OR ae.tech_code = ?) THEN 2
              ELSE 3
            END AS tier
        ", [$codeSal,$codeSal, $codeSal,$codeSal, $codeSal,$codeSal])
            ->where(function ($q) use ($agencesAutorisees) {
                foreach ($agencesAutorisees as $i => $ag) {
                    if (!is_string($ag) || $ag==='') continue;
                    $method = $i===0 ? 'where' : 'orWhere';
                    $q->{$method}('ae.NumInt', 'like', $ag.'%');
                }
            })
            ->orderBy('tier','asc')
            ->orderByRaw("COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) IS NULL ASC")
            ->orderByRaw("COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) ASC")
            ->orderBy('ae.NumInt','ASC');

        return $query->paginate($perPage);
    }


    /**
    * Variante non paginée (même logique de filtre/tri) si besoin ailleurs.
    */
    public function list(int $limit = 300, array $agencesAutorisees = [], ?string $codeSal = null): array
    {
    if (empty($agencesAutorisees)) {
    return [
    'rows'       => collect(),
    'counts'     => collect(),
    'nextIndex'  => null,
    'nextNumInt' => null,
    'total'      => 0,
    ];
    }

    $rows = DB::table('t_actions_etat as ae')
    ->selectRaw("
    ae.NumInt AS num_int,
    COALESCE(NULLIF(ae.contact_reel,''), '(contact inconnu)') AS client,
    COALESCE(NULLIF(ae.objet_traitement,''), 'À préciser') AS a_faire,
    DATE(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS date_prev,
    TIME(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS heure_prev,
    ae.tech_code AS code_tech,
    ae.reaffecte_code,
    ae.urgent AS urgent,
    CASE WHEN ae.reaffecte_code = ? OR ae.tech_code = ? THEN 1 ELSE 0 END AS concerne
    ", [$codeSal, $codeSal])
    ->where(function ($q) use ($agencesAutorisees) {
    foreach ($agencesAutorisees as $i => $ag) {
    if (!is_string($ag) || $ag === '') continue;
    $method = $i === 0 ? 'where' : 'orWhere';
    $q->{$method}('ae.NumInt', 'like', $ag . '%');
    }
    })
    ->orderByDesc('concerne')
    ->orderByDesc('urgent')
    ->orderByRaw("COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) IS NULL ASC")
    ->orderByRaw("COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) ASC")
    ->orderBy('ae.NumInt', 'ASC')
    ->limit($limit)
    ->get();

    $counts = $rows->groupBy('a_faire')->map->count();
    [$nextNumInt, $nextIndex] = $this->computeNext($rows);

    return [
    'rows'       => $rows,
    'counts'     => $counts,
    'nextIndex'  => $nextIndex,
    'nextNumInt' => $nextNumInt,
    'total'      => $rows->count(),
    ];
    }

    private function computeNext(Collection $rows): array
    {
    $bestIdx = null;
    $bestTs  = null;

    foreach ($rows as $idx => $r) {
    if (empty($r->date_prev) || empty($r->heure_prev)) continue;
    try {
    $ts = Carbon::parse($r->date_prev.' '.$r->heure_prev);
    } catch (\Throwable $e) {
    continue;
    }
    if ($bestTs === null || $ts->lt($bestTs)) {
    $bestTs  = $ts;
    $bestIdx = $idx;
    }
    }

    $num = $bestIdx !== null ? ($rows[$bestIdx]->num_int ?? null) : null;
    return [$num, $bestIdx];
    }
    }
