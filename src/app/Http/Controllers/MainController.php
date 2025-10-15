<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\TextRequest;
use App\Services\InterventionService;
use App\Services\NoteService;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{
    protected $authService;
    protected $interventionService;
    protected $noteService;

    public function __construct(AuthService $authService, InterventionService $interventionService, NoteService $noteService)
    {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
        $this->noteService = $noteService;
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
            ->orderByDesc('DateEnr')
            ->limit(500)
            ->pluck('NumInt');

        // $data vient du middleware (view()->share)
        return view('accueil', compact('numints'));
    }

    // app/Http/Controllers/MainController.php

    public function showInterventions(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10,25,50,100], true)) $perPage = 10;

        $rows = $this->interventionService->listPaginatedSimple($perPage);

        // URL JSON pour la page suivante (si mode scroll activé côté UI)
        $todoTagClass = [
            'CONFIRMER_RDV' => 'blue',
            'PLANIFIER_RDV' => 'amber',
            'CLOTURER'      => 'green',
            'DIAGNOSTIC'    => 'violet',
        ];

        return view('interventions.show', [
            'rows'         => $rows,
            'todoTagClass' => $todoTagClass,
            'perPage'      => $perPage,
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
        // 0) Intervention existante
        $interv = DB::table('t_intervention')->where('NumInt', $numInt)->first();
        if (!$interv) {
            return redirect()->route('accueil', ['id' => session('id')])
                ->with('error', 'Intervention introuvable.');
        }

        // 1) Note interne (service)
        $noteInterne = $this->noteService->getInternalNote($numInt);

        // 2) Vocabulaire (2 groupes)
        $vocab = DB::table('t_actions_vocabulaire')
            ->orderBy('group_code')
            ->orderBy('pos_index')
            ->get()
            ->groupBy('group_code'); // 'TRAITEMENT', 'AFFECTATION'

        // 3) État (bitstrings) + objet + contact
        $etat = DB::table('t_actions_etat')
            ->select('bits_traitement','bits_affectation','objet_traitement','contact_reel')
            ->where('NumInt', $numInt)
            ->first();

        $bitsTrait   = $etat->bits_traitement  ?? '';
        $bitsAffect  = $etat->bits_affectation ?? '';
        $objetTrait  = $etat->objet_traitement ?? '';
        $contactReel = $etat->contact_reel     ?? '';

        $isBitOn = static fn(string $bits, int $i) => isset($bits[$i]) && $bits[$i] === '1';

        // 4) Fabrique les items pour l’affichage
        $traitementItems = [];
        foreach (($vocab->get('TRAITEMENT') ?? collect()) as $row) {
            $traitementItems[] = [
                'code'      => $row->code,
                'pos_index' => (int) $row->pos_index,
                'label'     => $row->label,
                'checked'   => $isBitOn($bitsTrait, (int)$row->pos_index), // ✅ précoché
            ];
        }

        $affectationItems = [];
        foreach (($vocab->get('AFFECTATION') ?? collect()) as $row) {
            $affectationItems[] = [
                'code'      => $row->code,
                'pos_index' => (int) $row->pos_index,
                'label'     => $row->label,
                'checked'   => false, // ❌ jamais pré-coché (prochaines actions)
            ];
        }

        // 5) Techniciens & salariés pour les listes
        $agences = $GLOBALS['agences_autorisees'] ?? (view()->shared('agences_autorisees') ?? []);
        if (!is_array($agences) || empty($agences)) {
            $agences = [$interv->AgTrf ?? ($GLOBALS['data']->CodeAgSal ?? null)];
        }

        // Salariés (pour "Réaffecter à")
        $salaries = DB::table('t_salarie')
            ->when($agences, fn($q) => $q->whereIn('CodeAgSal', $agences))
            ->select('CodeSal','NomSal','CodeAgSal')
            ->orderBy('NomSal')
            ->limit(500)
            ->get();

        // Techniciens (pour agenda & affectation technicien)
        $techniciens = DB::table('t_salarie')
            ->when($agences, fn($q) => $q->whereIn('CodeAgSal', $agences))
            ->where(function($q){
                $q->where('fonction','like','TECH%')
                    ->orWhere('LibFonction','like','TECH%');
            })
            ->select('CodeSal','NomSal','CodeAgSal')
            ->orderBy('NomSal')
            ->limit(500)
            ->get();

        // 6) Historique serveur
        $suivis = DB::table('t_suiviclient_histo')
            ->where('NumInt', $numInt)
            ->orderByDesc('CreatedAt')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // 7) Heure serveur pour l’horloge locale
        $serverNow = now('Europe/Paris')->format('c'); // ISO8601

        $techCode = $interv->CodeTech ?? '';
// Date/heure prévues : ignore '0000-00-00' / '00:00:00'
        $techDate = (!empty($interv->DateIntPrevu) && $interv->DateIntPrevu !== '0000-00-00')
            ? $interv->DateIntPrevu
            : '';
        $techTime = (!empty($interv->HeureIntPrevu) && $interv->HeureIntPrevu !== '00:00:00')
            ? substr($interv->HeureIntPrevu, 0, 5)
            : '';

        return view('interventions.edit', [
            'interv'            => (object)['NumInt'=>$interv->NumInt],
            'traitementItems'   => $traitementItems,
            'affectationItems'  => $affectationItems,
            'noteInterne'       => $noteInterne,
            'objetTrait'        => $objetTrait,
            'contactReel'       => $contactReel,
            'salaries'          => $salaries,
            'techniciens'       => $techniciens,
            'suivis'            => $suivis,
            'serverNow'         => $serverNow,
            // NEW:
            'techCode'          => $techCode,
            'techDate'          => $techDate,
            'techTime'          => $techTime,
        ]);
    }

    /** API planning: prochains créneaux d’un technicien (5 jours par défaut) */
    // In MainController.php
    public function apiPlanningTech(Request $request, string $codeTech)
    {
        try {
            $from = $request->query('from');
            $to   = $request->query('to');

            if ($from && $to) {
                $start = \Carbon\Carbon::parse($from)->startOfDay();
                $end   = \Carbon\Carbon::parse($to)->endOfDay();
            } else {
                $days = max(1, min((int)$request->query('days', 5), 60));
                $start = now('Europe/Paris')->startOfDay();
                $end   = (clone $start)->addDays($days)->endOfDay();
            }

            // Base query on your real schema
            $q = DB::table('t_planning_technicien as p')
                ->select('p.CodeTech','p.StartDate','p.StartTime','p.EndDate','p.EndTime','p.NumIntRef','p.Label',
                    'e.contact_reel as Contact')
                ->leftJoin('t_actions_etat as e', 'e.NumInt', '=', 'p.NumIntRef')
                ->whereBetween('p.StartDate', [$start->toDateString(), $end->toDateString()])
                ->orderBy('p.StartDate')->orderBy('p.StartTime');

            if ($codeTech !== '_ALL') {
                $q->where('p.CodeTech', $codeTech);
            } else {
                // Optionally filter to techs list/agency if you want:
                // $allowedTechs = DB::table('t_salarie')->where(...)->pluck('CodeSal');
                // $q->whereIn('p.CodeTech', $allowedTechs);
            }

            $rows = $q->get();

            $events = $rows->map(function($r){
                $startIso = $r->StartDate . 'T' . ($r->StartTime ?? '00:00:00');
                $endIso   = $r->EndDate ? ($r->EndDate . 'T' . ($r->EndTime ?? '00:00:00')) : null;
                return [
                    'code_tech'       => $r->CodeTech,
                    'start_datetime'  => $startIso,
                    'end_datetime'    => $endIso,
                    'label'           => $r->Label,
                    'num_int'         => $r->NumIntRef,
                    'contact'         => $r->Contact ?? null,
                ];
            });

            return response()->json([
                'ok'     => true,
                'from'   => $start->format('Y-m-d'),
                'to'     => $end->format('Y-m-d'),
                'events' => $events,
            ]);
        } catch (\Throwable $e) {
            \Log::error('apiPlanningTech error', ['ex'=>$e->getMessage()]);
            return response()->json([
                'ok'  => false,
                'msg' => 'Erreur SQL lors de la lecture du planning',
                'sql' => [
                    'info'  => $e->getMessage(),
                    'code'  => method_exists($e,'getCode') ? $e->getCode() : null,
                    'state' => method_exists($e,'getSqlState') ? $e->getSqlState() : null,
                ],
            ], 500);
        }
    }





    public function updateInternalNote(Request $request, $numInt)
    {
        try {
            $validated = $request->validate([
                'id'   => ['required','string'],
                'note' => ['nullable','string','max:1000'],
            ], [
                'note.max' => 'Échec de l’enregistrement',
            ]);

            $exists = DB::table('t_intervention')->where('NumInt', $numInt)->exists();
            if (!$exists) {
                return response()->json(['ok'=>false,'msg'=>'Échec de l’enregistrement'], 404);
            }

            // ✅ écriture via service (cohérence lecture/écriture)
            $this->noteService->updateInternalNote($numInt, $validated['note']);

            return response()->json(['ok'=>true, 'msg'=>'Enregistré ✔']);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok'=>false, 'msg'=>'Échec de l’enregistrement'], 422);
        } catch (\Throwable $e) {
            \Log::error('updateInternalNote failed', [
                'numInt' => $numInt,
                'ex'     => $e->getMessage(),
            ]);
            return response()->json(['ok'=>false, 'msg'=>'Échec de l’enregistrement'], 500);
        }
    }



}
