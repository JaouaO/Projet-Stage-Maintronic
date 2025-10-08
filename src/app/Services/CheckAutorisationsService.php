<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use IntlDateFormatter;

class CheckAutorisationsService
{
    /**
     * Vérifie si un utilisateur a le droit d'accès selon son ID et les horaires.
     *
     * @param string|int $id
     * @return @return array           ['success' => bool, 'data' => objet|null]
     */
    public function checkAutorisations($id, $ipClient): array
    {
        $data = $this->getData($id);
        if (!$data) {
            return ['success' => false, 'data' => null];
        }

        $today = Carbon::now('Europe/Paris')->toDateString();
        // Vérifie que la date de t_log_util est bien aujourd'hui et l'IP correspond
        if ($data->DateAcces !== $today || $data->IP !== $ipClient) {
            return ['success' => false, 'data' => null];
        }

        $jour = $this->getJourCourant();
        $now  = Carbon::now('Europe/Paris')->format('H:i:s');

        if($this->isHoraireOk($data, $jour, $now)){
            return ['success' => true, 'data' => $data];
        }else{
            return ['success' => false, 'data' => null];
        }

    }

    /**
     * Récupère toutes les données nécessaires pour le check.
     */
    private function getData($id)
    {
        $today = Carbon::now('Europe/Paris')->toDateString();

        return DB::table('t_log_util as l')
            ->select([
                'l.id', 'l.IP', 'l.DateAcces', 'l.Demat',
                's.NomSal', 's.CodeAgSal',
                's.automenu1','s.automenu2','s.automenu3','s.automenu4',
                's.automenu5','s.automenu6','s.automenu7','s.automenu8',
                's.automenu9','s.automenu10','s.automenu11','s.automenu12',
                'h.*',
                'he.Date1','he.Date2',
                'he.HoraireJour1','he.HoraireJour2','he.HoraireJour3','he.HoraireJour4',
            ])
            ->leftJoin('t_salarie as s', 's.CodeSal', '=', 'l.Util')
            ->leftJoin('t_horaire as h', 'h.Code_Sal', '=', 'l.Util')
            ->leftJoin('t_horaireexcept as he', function($join) use ($today) {
                $join->on('he.Code_Sal', '=', 'l.Util')
                    ->where(function($q) use ($today) {
                        $q->where('he.Date1', '=', $today)
                            ->orWhere('he.Date2', '=', $today);
                    });
            })
            ->where('l.id', '=', $id)
            ->first();
    }

    /**
     * Retourne le jour courant au format "Lu", "Ma", "Me", etc.
     */
    private function getJourCourant()
    {
        $fmt = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Paris',
            IntlDateFormatter::GREGORIAN,
            'EEEE'
        );
        $jour = $fmt->format(Carbon::now('Europe/Paris'));
        return ucfirst(substr($jour, 0, 2));
    }

    /**
     * Vérifie si l'heure actuelle correspond à une plage autorisée (normale ou exceptionnelle),
     * en tenant compte des plages qui traversent minuit.
     */
    private function isHoraireOk($data, $jour, $now)
    {
        $plages = [
            // Plages normales (AM/PM)
            [$data->{$jour . '1'} ?? null, $data->{$jour . '2'} ?? null],
            [$data->{$jour . '3'} ?? null, $data->{$jour . '4'} ?? null],
            // Plages exceptionnelles (AM/PM)
            [$data->HoraireJour1 ?? null, $data->HoraireJour2 ?? null],
            [$data->HoraireJour3 ?? null, $data->HoraireJour4 ?? null],
        ];

        foreach ($plages as [$start, $end]) {
            if (!$start || !$end) continue;

            if ($start < $end) {
                if ($now > $start && $now < $end) return true;
            } else {
                // traverse minuit
                if ($now > $start || $now < $end) return true;
            }
        }

        return false;
    }
}
