<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\TextRequest;
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

    public function updateIntervention(Request $request, $numInt)
    {

$items = $request->all();
dump($items);
return view('test');

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



        $isTech  =true;
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
        $data = [
            'Marque'=>$marque,
            'DateIntPrevu' => $dateRdv ?? null,
            'HeureIntPrevu' => $heureRdv ?? null,
            'CommentInterne' => $noteInterne,
            'CodeTech' => ($isTech && !empty($reaTech)) ? $reaTech : null,
            'DateValid' => isset($dateRdv) ? Carbon::now()->toDateString() : null,
            'HeureValid' => isset($heureRdv) ? Carbon::now()->toTimeString() : null,
            'CPLivCli'=> $codePostal,
            'VilleLivCli' => $ville,
        ];

        DB::table('t_intervention')->updateOrInsert(
            ['NumInt' => $numInt],
            $data  // mêmes champs que tu utilises pour update/insert
        );


        //Ajout à t_planning_technicien
        if ($hasRdv && $isTech && !empty($reaTech)) {
            $end = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris')->addHour();
            $EndDate = $end->toDateString();   // même jour ou +1j si > 23h
            $EndTime = $end->format('H:i:s');  // heure +1h

            $data = [
                'CodeTech' => $reaTech,
                'StartTime' => $heureRdv,
                'EndTime' => $EndTime,
                'StartDate' => $dateRdv,
                'EndDate' => $EndDate,
                'NumIntRef' => $numInt,
                'Label' => 'Intervention pour le dossier ' . $numInt,
                'Commentaire' => $commentaire,
                'CPLivCli' => $codePostal,
                'VilleLivCli' => $ville,
            ];

            DB::table('t_planning_technicien')->insert($data);
        }

        //Ajout à t_suiviclient_histo
        $messageTraitement  = $this->traductionBit($vocabulaire, 'TRAITEMENT',  $bitsTraitement);

        DB::table('t_suiviclient_histo')->insert([
            'NumInt'        => $numInt,
            'CreatedAt'     => now(),
            'CodeSalAuteur' =>$codeSal,
            'Titre'         => $objet_trait,
            'Texte'         => ($messageTraitement ? "TRAITEMENT : $messageTraitement\n" : '')
                ."COMMENTAIRE : ".$commentaire,
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
        if(isset($reaTech) && $hasRdv){
            $data['tech_code'] = $reaTech;
            $data['tech_rdv_at'] = Carbon::parse("$dateRdv $heureRdv", 'Europe/Paris');
        }

        DB::table('t_actions_etat')->updateOrInsert(
            ['NumInt' => $numInt],
            $data
        );

        return redirect('accueil');

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
