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

    public function __construct(AuthService $authService, InterventionService $interventionService, TraitementDossierService $traitementDossierService, PlanningService $planningService)
    {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
        $this->traitementDossierService = $traitementDossierService;
        $this->planningService = $planningService;
    }

    public function showLoginForm()
    {
        // Si déjà connecté, redirige directement
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
            return redirect()
                ->route('erreur')
                ->with('message', $login['message']);
        }

        $ip = $request->ip();
        $id = $this->authService->generateId();

        $result = $this->authService->verifHoraires($codeSal);

        $request->session()->put([
            'id' => $id,
            'codeSal' => $login['user']['codeSal'],
            'CodeAgSal' => $login['user']['CodeAgSal'],
        ]);
        if ($result['success']) {
            $this->authService->logAccess($id, $ip, $codeSal, $login['user']['CodeAgSal']);
            return redirect()->route('accueil', ['id' => $id]);
        }

        return redirect()
            ->route('erreur')
            ->with('message', $result['message']);

    }

    public function accueil(NotBlankRequest $request)
    {
        // Liste légère pour l’autocomplete (adapte la limite si besoin)
        $numints = DB::table('t_intervention')
            ->orderByDesc('DateIntPrevu')
            ->orderByDesc('HeureIntPrevu')
            ->where('CodeTech',null)
            ->limit(500)
            ->pluck('NumInt');

        // $data vient du middleware (view()->share)
        return view('accueil', compact('numints'));
    }

    // app/Http/Controllers/MainController.php

    public function showInterventions(Request $request)
    {
        $perPage = (int)$request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 10;

        $rows = $this->interventionService->listPaginatedSimple($perPage);

        $todoTagClass = [
            'CONFIRMER_RDV' => 'blue',
            'PLANIFIER_RDV' => 'amber',
            'CLOTURER' => 'green',
            'DIAGNOSTIC' => 'violet',
        ];

        return view('interventions.show', [
            'rows' => $rows,
            'todoTagClass' => $todoTagClass,
            'perPage' => $perPage,
        ]);
    }

    public function entree(NotBlankRequest $request)
    {
        $idFromUrl = $request->query('id'); // conservé par ton middleware
        $sessionId = session('id');
        if (!$sessionId || $idFromUrl !== $sessionId) {
            return redirect()->route('authentification')->with('message', 'Session invalide.');
        }

        // Validation
        $validated = $request->validate([
            'num_int' => ['required', 'regex:/^[A-Za-z0-9_-]+$/', 'exists:t_intervention,NumInt'],
            'agence' => ['required', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        // Ici, si tout est OK → page A
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

    /** API planning: prochains créneaux d’un technicien (5 jours par défaut) */
    // In MainController.php
    public function apiPlanningTech(Request $request, $codeTech) // $codeTech typé string possible aussi en 7.4
    {
        try {
            $payload = $this->planningService->getPlanning(
                $codeTech,
                $request->query('from'),
                $request->query('to'),
                (int)$request->query('days', 5),
                'Europe/Paris'
            // , $allowedTechs  // <-- optionnel si tu veux restreindre _ALL
            );

            return response()->json($payload);

        } catch (\Throwable $e) {
            \Log::error('apiPlanningTech error', ['ex' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'msg' => 'Erreur SQL lors de la lecture du planning',
                'sql' => [
                    'info' => $e->getMessage(),
                    'code' => method_exists($e, 'getCode') ? $e->getCode() : null,
                    'state' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
                ],
            ], 500);
        }
    }

    public function updateIntervention(UpdateInterventionRequest $request, $numInt)
    {


        $commentaire = trim((string)$request->input('commentaire', ''));
        $contactReel = trim((string)$request->input('contact_reel', ''));
        $reaSal = $request->input('rea_sal');  // si tu l’as côté SAL
        $dateRdv = $request->input('date_rdv'); // 'YYYY-MM-DD'
        $heureRdv = $request->input('heure_rdv'); // 'HH:MM' (ou null)
        $traitement = (array)$request->input('traitement', []);
        $affectation = (array)$request->input('affectation', []);
        $numInt = trim((string)$numInt);
        $codeSal = $request->input('code_sal_auteur');
        $codePostal = $request->input('code_postal');
        $ville = $request->input('ville');
        $marque = $request->input('marque');
        $objet_trait = $request->input('objet_trait');
        $actionType = $request->input('action_type', ''); // 'call' | 'validate_rdv' | ''

        $hasRdv  = !empty($dateRdv) && !empty($heureRdv);


        $vocab = DB::table('t_actions_vocabulaire')
            ->orderBy('pos_index')
            ->get();

        $vocabulaire = [
            'labels' => [],          // clé: "GROUPExINDEX" => label
            'codes'  => []           // clé: "GROUPE" => [pos_index => code]
        ];

        foreach ($vocab as $item) {
            $vocabulaire['labels'][$item->group_code.$item->pos_index] = $item->label;
            $vocabulaire['codes'][$item->group_code][(int)$item->pos_index] = $item->code;
        }

        $bitsTraitement  = $this->bitsFromPosted($traitement,  'TRAITEMENT',  $vocabulaire);
        $bitsAffectation = $this->bitsFromPosted($affectation, 'AFFECTATION', $vocabulaire);



        //Ajout/Update T_intervention:
        $nowParis = \Carbon\Carbon::now('Europe/Paris');

        $dataInterv = [
            'Marque' => $marque, // si tu veux aussi conditionner, tu peux le faire comme ci-dessous
        ];

// petit helper local
        $put = static function(array &$arr, string $key, $val): void {
            if ($val !== null && $val !== '') {
                $arr[$key] = $val;
            }
        };

// On n’affecte un champ que s’il est présent/non vide dans la requête
        if ($actionType !== 'call') {
            $put($dataInterv, 'DateIntPrevu',  $dateRdv);
            $put($dataInterv, 'HeureIntPrevu', $heureRdv);
            $put($dataInterv, 'CodeTech',      $reaSal);

            if (!empty($dateRdv)) {
                $dataInterv['DateValid'] = $nowParis->toDateString();
            }
            if (!empty($heureRdv)) {
                $dataInterv['HeureValid'] = $nowParis->format('H:i:s');
            }
        }
        $put($dataInterv, 'CPLivCli',      $codePostal);
        $put($dataInterv, 'VilleLivCli',   $ville);


// Si tu veux aussi éviter d’écraser Marque quand elle est vide :
        if ($marque === null || $marque === '') {
            unset($dataInterv['Marque']);
        }

        DB::table('t_intervention')->updateOrInsert(
            ['NumInt' => $numInt],
            $dataInterv
        );


        $shouldInsertPlanning =
            ($actionType !== 'call')           // on ne crée PAS de RDV pour un appel
            && $hasRdv
            && !empty($reaSal);

        if ($shouldInsertPlanning) {
            $end = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris')->addHour();
            $EndDate = $end->toDateString();   // même jour ou +1j si > 23h
            $EndTime = $end->format('H:i:s');  // heure +1h

            $labelComment = trim($commentaire) !== '' ? mb_substr($commentaire, 0, 60) : '';
            $label = trim($numInt . ' -- ' . $labelComment);

            $data = [
                'CodeTech' => $reaSal,
                'StartTime' => $heureRdv,
                'EndTime' => $EndTime,
                'StartDate' => $dateRdv,
                'EndDate' => $EndDate,
                'NumIntRef' => $numInt,
                'Label' => $label,
                'Commentaire' => $commentaire,
                'CPLivCli' => $codePostal,
                'VilleLivCli' => $ville,
                'IsValidated'  => 1,
            ];

            DB::table('t_planning_technicien')->insert($data);
        }

        //Ajout à t_suiviclient_histo

        $evtType = null;
        $evtMeta = null;

// 1) Bouton "Planifier un appel"
        if ($actionType === 'call') {
            $evtType = 'CALL_PLANNED';
            $evtMeta = [
                'date'  => $dateRdv ?: null,
                'heure' => $heureRdv ?: null,
                'tech'  => $reaSal ?: null,
                'cp'    => $codePostal ?: null,
                'ville' => $ville ?: null,
                'label' => mb_substr(trim((string)$commentaire), 0, 60) ?: null,
            ];
        }

// 2) Soumission classique qui crée un RDV validé (sans passer par /rdv/valider)
        if (!$evtType && $shouldInsertPlanning) {
            $evtType = 'RDV_FIXED';
            $evtMeta = [
                'date'  => $dateRdv,
                'heure' => $heureRdv,
                'tech'  => $reaSal,
                'cp'    => $codePostal ?: null,
                'ville' => $ville ?: null,
                'label' => $labelComment ?: null,
            ];
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


        //t_action_etat
        $messageAffectation = $this->traductionBit($vocabulaire, 'AFFECTATION', $bitsAffectation);
        $data = [
            'bits_traitement' => $bitsTraitement,
            'bits_affectation' => $bitsAffectation,
            'objet_traitement' => $messageAffectation,
            'contact_reel'=>$contactReel,
        ];

        if(isset($reaSal) && $hasRdv){
            $data['reaffecte_code'] = $reaSal;
            $data['rdv_prev_at'] = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris');
        }
        if(isset($reaSal) && $hasRdv){
            $data['tech_code'] = $reaSal;
            $data['tech_rdv_at'] = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris');
        }

        DB::table('t_actions_etat')->updateOrInsert(
            ['NumInt' => $numInt],
            $data
        );

        return redirect('accueil');

    }

    public function rdvTemporaire(Request $request, string $numInt)
    {
        try {
            $data = $request->validate([
                'rea_sal'   => ['required','string','max:5'],
                'date_rdv'  => ['required','date_format:Y-m-d'],
                'heure_rdv' => ['required','date_format:H:i'],
                'commentaire' => ['nullable','string','max:250'],
            ]);

            $tech  = $data['rea_sal'];
            $date  = $data['date_rdv'];
            $heure = $data['heure_rdv'];
            $commentaire = $data['commentaire'] ?? null;

            $start = \Carbon\Carbon::parse("$date $heure",'Europe/Paris');
            $end   = (clone $start)->addHour();

            // Mise à jour si créneau identique existe déjà (même NumInt + date + heure)
            $exists = DB::table('t_planning_technicien')->where([
                ['NumIntRef', '=', $numInt],
                ['StartDate', '=', $start->toDateString()],
                ['StartTime', '=', $start->format('H:i:s')],
            ])->first();

            $labelComment = $commentaire ? mb_substr($commentaire, 0, 60) : '';
            $label = trim($numInt . ' -- ' . $labelComment);

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
            ];

            if ($exists) {
                DB::table('t_planning_technicien')->where('id', $exists->id)->update($payload);
                $mode = 'updated';
            } else {
                DB::table('t_planning_technicien')->insert($payload);
                $mode = 'inserted';
            }

            DB::table('t_intervention')->updateOrInsert(
                ['NumInt' => $numInt],
                [
                    'DateIntPrevu'  => $start->toDateString(),
                    'HeureIntPrevu' => $start->format('H:i:s'),
                    'CodeTech'      => $tech,
                ]
            );
            $evtMeta = [
                'date'  => $start->toDateString(),
                'heure' => $start->format('H:i'),
                'tech'  => $tech,
                'cp'    => $request->input('code_postal') ?: null,
                'ville' => $request->input('ville') ?: null,
                'label' => $labelComment ?: null,
            ];

            DB::table('t_suiviclient_histo')->insert([
                'NumInt'        => $numInt,
                'CreatedAt'     => now('Europe/Paris'),
                'CodeSalAuteur' => session('codeSal') ?: null, // ou $request->input('code_sal_auteur')
                'Titre'         => 'Planification',
                'Texte'         => (string)($commentaire ?: ''),
                'evt_type'      => $mode === 'updated' ? 'RDV_TEMP_UPDATED' : 'RDV_TEMP_INSERTED',
                'evt_meta'      => json_encode($evtMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);

            return response()->json(['ok'=>true, 'mode'=>$mode]);
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
            return response()->json(['ok'=>false, 'errors'=>$ve->errors()], 422);
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
                ->where(function($w){
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
            return response()->json(['ok'=>false, 'msg'=>$e->getMessage()], 500);
        }
    }

    public function rdvTempPurge(Request $request, string $numInt)
    {
        try {
            $deleted = DB::table('t_planning_technicien')
                ->where('NumIntRef', $numInt)
                ->where(function($w){
                    $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
                })
                ->delete();
            return response()->json(['ok'=>true, 'deleted'=>$deleted]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false, 'msg'=>$e->getMessage()], 500);
        }
    }


    private function traductionBit(array $vocabulaire, string $groupeCode, string $bits): string
    {
        $index = 0;
        $traduction = '';
        foreach (str_split($bits) as $bit) {
            if ($bit) { // "1" => true ; "0" => false en PHP
                $key = $groupeCode.$index;
                $label = $vocabulaire['labels'][$key] ?? null;
                if ($label) {
                    $traduction .= ($traduction === '' ? '' : ', ').$label;
                }
            }
            $index++;
        }
        return $traduction;
    }

    private function bitsFromPosted(array $posted, string $group, array $vocabulaire): string
    {
        // Tableau: [pos_index => code] pour le groupe demandé
        $map = $vocabulaire['codes'][$group] ?? [];
        if (empty($map)) return '';

        $max = (int)max(array_keys($map));
        $bits = '';

        for ($i = 0; $i <= $max; $i++) {
            $code = $map[$i] ?? null;
            $bits .= ($code && isset($posted[$code]) && (string)$posted[$code] === '1') ? '1' : '0';
        }
        return $bits;
    }



}
