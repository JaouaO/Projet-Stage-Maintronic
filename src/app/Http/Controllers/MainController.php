<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\TextRequest;
use App\Http\Requests\UpdateInterventionRequest;
use App\Services\InterventionService;
use App\Services\PlanningService;
use App\Services\TraitementDossierService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{
    protected $authService;
    protected $interventionService;
    private $traitementDossierService;
    private $planningService;

    public function __construct(
        AuthService $authService,
        InterventionService $interventionService,
        TraitementDossierService $traitementDossierService,
        PlanningService $planningService
    ) {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
        $this->traitementDossierService = $traitementDossierService;
        $this->planningService = $planningService;
    }

    public function showLoginForm()
    {
        if (session()->has('id')) {
            return redirect()->route('accueil', ['id' => session('id')]);
        }
        return view('login');
    }

    public function login(NotBlankRequest $request)
    {
        $codeSal = $request->input('codeSal');
        $login = $this->authService->login($codeSal);

        if (!$login['success']) {
            return redirect()->route('erreur')->with('message', $login['message']);
        }

        $ip = $request->ip();
        $id = $this->authService->generateId();

        $result = $this->authService->verifHoraires($codeSal);

        $request->session()->put([
            'id'        => $id,
            'codeSal'   => $login['user']['codeSal'],
            'CodeAgSal' => $login['user']['CodeAgSal'],
        ]);

        if ($result['success']) {
            $this->authService->logAccess($id, $ip, $codeSal, $login['user']['CodeAgSal']);
            return redirect()->route('accueil', ['id' => $id]);
        }

        return redirect()->route('erreur')->with('message', $result['message']);
    }

    public function accueil(NotBlankRequest $request)
    {
        $numints = DB::table('t_intervention')
            ->orderByDesc('DateIntPrevu')
            ->orderByDesc('HeureIntPrevu')
            ->whereNull('CodeTech')
            ->limit(500)
            ->pluck('NumInt');

        return view('accueil', compact('numints'));
    }

    public function showInterventions(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 10;

        $rows = $this->interventionService->listPaginatedSimple($perPage);

        $todoTagClass = [
            'CONFIRMER_RDV' => 'blue',
            'PLANIFIER_RDV' => 'amber',
            'CLOTURER'      => 'green',
            'DIAGNOSTIC'    => 'violet',
        ];

        return view('interventions.show', [
            'rows'        => $rows,
            'todoTagClass'=> $todoTagClass,
            'perPage'     => $perPage,
        ]);
    }

    public function entree(NotBlankRequest $request)
    {
        $idFromUrl  = $request->query('id');
        $sessionId  = session('id');
        if (!$sessionId || $idFromUrl !== $sessionId) {
            return redirect()->route('authentification')->with('message', 'Session invalide.');
        }

        $validated = $request->validate([
            'num_int' => ['required', 'regex:/^[A-Za-z0-9_-]+$/', 'exists:t_intervention,NumInt'],
            'agence'  => ['required', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        return redirect()->route('interv.edit', ['numInt' => $validated['num_int']]);
    }

    public function editIntervention($numInt)
    {
        $payload = $this->traitementDossierService->loadEditPayload($numInt);

        if (!$payload['interv']) {
            return redirect()
                ->route('accueil', ['id' => session('id')])
                ->with('error', 'Intervention introuvable.');
        }

        return view('interventions.edit', $payload);
    }

    /** API planning */
    public function apiPlanningTech(Request $request, $codeTech)
    {
        try {
            $payload = $this->planningService->getPlanning(
                $codeTech,
                $request->query('from'),
                $request->query('to'),
                (int) $request->query('days', 5),
                'Europe/Paris'
            );
            return response()->json($payload);
        } catch (\Throwable $e) {
            \Log::error('apiPlanningTech error', ['ex' => $e->getMessage()]);
            return response()->json([
                'ok'  => false,
                'msg' => 'Erreur SQL lors de la lecture du planning',
                'sql' => [
                    'info'  => $e->getMessage(),
                    'code'  => method_exists($e, 'getCode') ? $e->getCode() : null,
                    'state' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
                ],
            ], 500);
        }
    }

    public function updateIntervention(UpdateInterventionRequest $request, $numInt)
    {
        $commentaire = trim((string) $request->input('commentaire', ''));
        $contactReel = trim((string) $request->input('contact_reel', ''));
        $reaSal      = $request->input('rea_sal');            // technicien choisi
        $dateRdv     = $request->input('date_rdv');           // YYYY-MM-DD
        $heureRdv    = $request->input('heure_rdv');          // HH:MM
        $traitement  = (array) $request->input('traitement', []);
        $affectation = (array) $request->input('affectation', []);
        $numInt      = trim((string) $numInt);
        $codeSal     = $request->input('code_sal_auteur');
        $codePostal  = $request->input('code_postal');
        $ville       = $request->input('ville');
        $marque      = $request->input('marque');
        $objet_trait = $request->input('objet_trait');
        $actionType  = $request->input('action_type', '');    // 'call' | 'validate_rdv' | ''
        $urgent      = $request->boolean('urgent');           // <-- NOUVEAU

        $hasRdv = !empty($dateRdv) && !empty($heureRdv);
        $labelComment = $commentaire !== '' ? mb_substr($commentaire, 0, 60) : '';

        // Vocabulaire
        $vocab = DB::table('t_actions_vocabulaire')->orderBy('pos_index')->get();
        $vocabulaire = ['labels' => [], 'codes' => []];
        foreach ($vocab as $item) {
            $vocabulaire['labels'][$item->group_code . $item->pos_index] = $item->label;
            $vocabulaire['codes'][$item->group_code][(int) $item->pos_index] = $item->code;
        }

        $bitsTraitement  = $this->bitsFromPosted($traitement,  'TRAITEMENT',  $vocabulaire);
        $bitsAffectation = $this->bitsFromPosted($affectation, 'AFFECTATION', $vocabulaire);

        // --- Mise à jour t_intervention (si pas "call")
        $nowParis  = Carbon::now('Europe/Paris');
        $dataInterv = ['Marque' => $marque];

        $put = static function (array &$arr, string $key, $val): void {
            if ($val !== null && $val !== '') $arr[$key] = $val;
        };

        if ($actionType !== 'call') {
            $put($dataInterv, 'DateIntPrevu',  $dateRdv);
            $put($dataInterv, 'HeureIntPrevu', $heureRdv);
            $put($dataInterv, 'CodeTech',      $reaSal);

            if (!empty($dateRdv))  $dataInterv['DateValid'] = $nowParis->toDateString();
            if (!empty($heureRdv)) $dataInterv['HeureValid'] = $nowParis->format('H:i:s');
        }
        $put($dataInterv, 'CPLivCli',    $codePostal);
        $put($dataInterv, 'VilleLivCli', $ville);

        if ($marque === null || $marque === '') {
            unset($dataInterv['Marque']);
        }

        DB::table('t_intervention')->updateOrInsert(['NumInt' => $numInt], $dataInterv);

        // --- Création RDV VALIDÉ => planning (IsUrgent) ---
        $shouldInsertPlanning = ($actionType !== 'call') && $hasRdv && !empty($reaSal);
        if ($shouldInsertPlanning) {
            $end     = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris')->addHour();
            $EndDate = $end->toDateString();
            $EndTime = $end->format('H:i:s');

            $label = trim($numInt . ' — ' . $labelComment); // tiret cadratin U+2014

            $dataPlanning = [
                'CodeTech'     => $reaSal,
                'StartTime'    => $heureRdv,
                'EndTime'      => $EndTime,
                'StartDate'    => $dateRdv,
                'EndDate'      => $EndDate,
                'NumIntRef'    => $numInt,
                'Label'        => $label,
                'Commentaire'  => $commentaire,
                'CPLivCli'     => $codePostal,
                'VilleLivCli'  => $ville,
                'IsValidated'  => 1,
                'IsUrgent'     => $urgent ? 1 : 0,   // <-- NOUVEAU
            ];

            DB::table('t_planning_technicien')->insert($dataPlanning);
        }

        // --- Historique (t_suiviclient_histo) ---
        $evtType = null;
        $evtMeta = null;

        if ($actionType === 'call') {
            $evtType = 'CALL_PLANNED';
            $evtMeta = $this->pruneNulls([
                'd'   => $dateRdv ?? null,
                'h'   => $heureRdv ?? null,
                't'   => $reaSal   ?? null,
                'cp'  => $codePostal ?: null,
                'v'   => $ville      ?: null,
                'lab' => $labelComment ?: null,
                'tb'  => $bitsTraitement  ?: null,
                'ab'  => $bitsAffectation ?: null,
                'tl'  => $this->labelsFromBits($vocabulaire, 'TRAITEMENT',  $bitsTraitement)  ?: null,
                'al'  => $this->labelsFromBits($vocabulaire, 'AFFECTATION', $bitsAffectation) ?: null,
                'urg' => $urgent ? 1 : null,   // visible dans l’historique aussi
            ]);
        }

        if (!$evtType && $shouldInsertPlanning) {
            $evtType = 'RDV_FIXED';
            $evtMeta = $this->pruneNulls([
                'd'   => $dateRdv ?? null,
                'h'   => $heureRdv ?? null,
                't'   => $reaSal   ?? null,
                'cp'  => $codePostal ?: null,
                'v'   => $ville      ?: null,
                'lab' => $labelComment ?: null,
                'tb'  => $bitsTraitement  ?: null,
                'ab'  => $bitsAffectation ?: null,
                'tl'  => $this->labelsFromBits($vocabulaire, 'TRAITEMENT',  $bitsTraitement)  ?: null,
                'al'  => $this->labelsFromBits($vocabulaire, 'AFFECTATION', $bitsAffectation) ?: null,
                'urg' => $urgent ? 1 : null,   // <-- NOUVEAU
            ]);
        }

        DB::table('t_suiviclient_histo')->insert([
            'NumInt'        => $numInt,
            'CreatedAt'     => now('Europe/Paris'),
            'CodeSalAuteur' => $codeSal,
            'Titre'         => $objet_trait,
            'Texte'         => $commentaire,
            'evt_type'      => $evtType,
            'evt_meta'      => $evtMeta ? json_encode($evtMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        ]);

        // --- t_actions_etat (snapshot dossier + urgent) ---
        $messageAffectation = $this->traductionBit($vocabulaire, 'AFFECTATION', $bitsAffectation);
        $dataEtat = [
            'bits_traitement'  => $bitsTraitement,
            'bits_affectation' => $bitsAffectation,
            'objet_traitement' => $messageAffectation, // <— tu confirmes que c’est voulu
            'contact_reel'     => $contactReel,
            'urgent'           => $urgent ? 1 : 0,     // <-- NOUVEAU
        ];

        if (isset($reaSal) && $hasRdv) {
            $dataEtat['reaffecte_code'] = $reaSal;
            $dataEtat['rdv_prev_at']    = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris');
        }
        if (isset($reaSal) && $hasRdv) {
            $dataEtat['tech_code']   = $reaSal;
            $dataEtat['tech_rdv_at'] = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris');
        }

        DB::table('t_actions_etat')->updateOrInsert(['NumInt' => $numInt], $dataEtat);

        return redirect('accueil');
    }

    public function rdvTemporaire(Request $request, string $numInt)
    {
        try {
            $data = $request->validate([
                'rea_sal'     => ['required','string','max:5'],
                'date_rdv'    => ['required','date_format:Y-m-d'],
                'heure_rdv'   => ['required','date_format:H:i'],
                'commentaire' => ['nullable','string','max:250'],
            ]);

            $tech        = $data['rea_sal'];
            $date        = $data['date_rdv'];
            $heure       = $data['heure_rdv'];
            $commentaire = $data['commentaire'] ?? null;

            $start = Carbon::parse("$date $heure", 'Europe/Paris');
            $end   = (clone $start)->addHour();

            $exists = DB::table('t_planning_technicien')->where([
                ['NumIntRef', '=', $numInt],
                ['StartDate', '=', $start->toDateString()],
                ['StartTime', '=', $start->format('H:i:s')],
            ])->first();

            $labelComment = $commentaire ? mb_substr($commentaire, 0, 60) : '';
            $label = trim($numInt . ' — ' . $labelComment); // tiret cadratin U+2014
            $payload = [
                'CodeTech'    => $tech,
                'StartDate'   => $start->toDateString(),
                'StartTime'   => $start->format('H:i:s'),
                'EndDate'     => $end->toDateString(),
                'EndTime'     => $end->format('H:i:s'),
                'NumIntRef'   => $numInt,
                'Label'       => $label,
                'Commentaire' => $commentaire,
                'CPLivCli'    => $request->input('code_postal') ?: null,
                'VilleLivCli' => $request->input('ville') ?: null,
                'IsValidated' => 0,
                // PAS d’urgent ici (temporaire) => IsUrgent par défaut à 0
            ];

            if ($exists) {
                DB::table('t_planning_technicien')->where('id', $exists->id)->update($payload);
                $mode = 'updated';
            } else {
                DB::table('t_planning_technicien')->insert($payload);
                $mode = 'inserted';
            }

            // MàJ légère t_intervention (prévision)
            DB::table('t_intervention')->updateOrInsert(
                ['NumInt' => $numInt],
                [
                    'DateIntPrevu'  => $start->toDateString(),
                    'HeureIntPrevu' => $start->format('H:i:s'),
                    'CodeTech'      => $tech,
                ]
            );

            // Historique : evt_meta SANS urg (demande explicite)
            $evtMeta = $this->pruneNulls([
                'd'   => $start->toDateString(),
                'h'   => $start->format('H:i'),
                't'   => $tech,
                'lab' => $labelComment ?: null,
            ]);

            DB::table('t_suiviclient_histo')->insert([
                'NumInt'        => $numInt,
                'CreatedAt'     => now('Europe/Paris'),
                'CodeSalAuteur' => session('codeSal') ?: null,
                'Titre'         => 'Planification',
                'Texte'         => (string) ($commentaire ?: ''),
                'evt_type'      => $mode === 'updated' ? 'RDV_TEMP_UPDATED' : 'RDV_TEMP_INSERTED',
                'evt_meta'      => json_encode($evtMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);

            return response()->json(['ok' => true, 'mode' => $mode]);

        } catch (\Illuminate\Database\QueryException $qe) {
            $ei = $qe->errorInfo ?? [];
            return response()->json([
                'ok'       => false,
                'type'     => 'QueryException',
                'sqlstate' => $ei[0] ?? null,
                'errno'    => $ei[1] ?? null,
                'errmsg'   => $ei[2] ?? $qe->getMessage(),
                'file'     => basename($qe->getFile()) . ':' . $qe->getLine(),
            ], 500);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'errors' => $ve->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'     => false,
                'type'   => 'Throwable',
                'errmsg' => $e->getMessage(),
                'file'   => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    public function rdvTempCheck(Request $request, string $numInt)
    {
        try {
            $rows = DB::table('t_planning_technicien')
                ->where('NumIntRef', $numInt)
                ->where(function ($w) {
                    $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
                })
                ->orderBy('StartDate')->orderBy('StartTime')
                ->get(['id','CodeTech','StartDate','StartTime','Label']);

            return response()->json([
                'ok'    => true,
                'count' => $rows->count(),
                'items' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    public function rdvTempPurge(Request $request, string $numInt)
    {
        try {
            $deleted = DB::table('t_planning_technicien')
                ->where('NumIntRef', $numInt)
                ->where(function ($w) {
                    $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
                })
                ->delete();

            return response()->json(['ok' => true, 'deleted' => $deleted]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ===== Helpers =====

    private function traductionBit(array $vocabulaire, string $groupeCode, string $bits): string
    {
        $index = 0;
        $parts = [];
        foreach (str_split((string) $bits) as $bit) {
            if ($bit === '1') {
                $key = $groupeCode . $index;
                if (isset($vocabulaire['labels'][$key])) $parts[] = $vocabulaire['labels'][$key];
            }
            $index++;
        }
        return implode(', ', $parts);
    }

    private function labelsFromBits(array $vocabulaire, string $groupeCode, string $bits): array
    {
        $index = 0;
        $out = [];
        foreach (str_split((string) $bits) as $bit) {
            if ($bit === '1') {
                $key = $groupeCode . $index;
                if (isset($vocabulaire['labels'][$key])) $out[] = $vocabulaire['labels'][$key];
            }
            $index++;
        }
        return $out;
    }

    private function pruneNulls(array $arr): array
    {
        return array_filter($arr, static fn ($v) => $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
    }

    private function bitsFromPosted(array $posted, string $group, array $vocabulaire): string
    {
        $map = $vocabulaire['codes'][$group] ?? [];
        if (empty($map)) return '';

        $max  = (int) max(array_keys($map));
        $bits = '';
        for ($i = 0; $i <= $max; $i++) {
            $code = $map[$i] ?? null;
            $bits .= ($code && isset($posted[$code]) && (string) $posted[$code] === '1') ? '1' : '0';
        }
        return $bits;
    }
}
