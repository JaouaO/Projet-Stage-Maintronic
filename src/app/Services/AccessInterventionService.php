<?php
namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessInterventionService
{
    private function agenceFromNumInt(string $numInt): string
    {
        $base = explode('-', $numInt, 2)[0] ?? '';
        return mb_substr($base, 0, 4);
    }

    /**
     * Priorité : interne > direction > externe
     * Retour : Collection d'objets { CodeSal, NomSal, CodeAgSal, access_level }
     */
    public function listPeopleForNumInt(string $numInt): Collection
    {
        $ag = $this->agenceFromNumInt($numInt);
        $startMC = preg_match('/^[MC]/', $ag) === 1;

        // petit helper pour le test Obsolete (NULL autorisé)
        $notObsolete = function ($q) {
            $q->whereNull('s.Obsolete')->orWhere('s.Obsolete', '!=', 'O');
        };

        // --- Interne ---
        $qInterne = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'interne' as access_level")
            ->where($notObsolete)
            ->where('s.CodeAgSal', $ag);

        // --- Direction (PLUS + t_resp Defaut = 'O') ---
        $qDirection = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'direction' as access_level")
            ->where($notObsolete)
            ->where('s.CodeAgSal', 'PLUS')
            ->whereExists(function ($q) use ($ag) {
                $q->from('t_resp as r')
                    ->whereColumn('r.CodeSal', 's.CodeSal')
                    ->where('r.CodeAgSal', $ag)
                    ->where('r.Defaut', 'O');
            });

        // --- Externe ---
        // (i) PLUS + t_resp Defaut = 'N'
        $qExtPlus = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'externe' as access_level")
            ->where($notObsolete)
            ->where('s.CodeAgSal', 'PLUS')
            ->whereExists(function ($q) use ($ag) {
                $q->from('t_resp as r')
                    ->whereColumn('r.CodeSal', 's.CodeSal')
                    ->where('r.CodeAgSal', $ag)
                    ->where('r.Defaut', 'N');
            });

        // (ii) DOAG si agence commence par M ou C
        $qExtDoag = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'externe' as access_level")
            ->where($notObsolete)
            ->when($startMC, fn ($q) => $q->where('s.CodeAgSal', 'DOAG'), fn ($q) => $q->whereRaw('0=1'));

        // (iii) Tous les ADMI
        $qExtAdmi = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'externe' as access_level")
            ->where($notObsolete)
            ->where('s.CodeAgSal', 'ADMI');

        // UNION (sans fromSub / DB::query) + tri global
        $union = $qInterne
            ->unionAll($qDirection)
            ->unionAll($qExtPlus)
            ->unionAll($qExtDoag)
            ->unionAll($qExtAdmi);

        // L'ORDER BY placé ici s'applique à tout le UNION dans MySQL
        $rows = $union
            ->orderByRaw("FIELD(access_level, 'interne','direction','externe')")
            ->orderBy('NomSal')
            ->get();

        // Dédoublonnage par CodeSal avec priorité interne > direction > externe
        $priority = ['interne' => 3, 'direction' => 2, 'externe' => 1];

        return collect($rows)
            ->groupBy('CodeSal')
            ->map(function ($grp) use ($priority) {
                return $grp->sortByDesc(fn ($r) => $priority[$r->access_level] ?? 0)->first();
            })
            ->values();
    }
}
