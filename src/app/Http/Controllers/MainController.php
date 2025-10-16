<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\TextRequest;
use App\Services\InterventionService;
use App\Services\NoteService;
use App\Services\PlanningService;
use App\Services\TraitementDossierService;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{
    protected $authService;
    protected $interventionService;
    protected $noteService;
    private $traitementDossierService;
    private $planningService;

    public function __construct(AuthService $authService, InterventionService $interventionService, NoteService $noteService, TraitementDossierService $traitementDossierService, PlanningService $planningService)
    {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
        $this->noteService = $noteService;
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
            ->orderByDesc('DateEnr')
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


    public function updateInternalNote(Request $request, $numInt)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'string'],
                'note' => ['nullable', 'string', 'max:1000'],
            ], [
                'note.max' => 'Échec de l’enregistrement',
            ]);

            $exists = DB::table('t_intervention')->where('NumInt', $numInt)->exists();
            if (!$exists) {
                return response()->json(['ok' => false, 'msg' => 'Échec de l’enregistrement'], 404);
            }

            // ✅ écriture via service (cohérence lecture/écriture)
            $this->noteService->updateInternalNote($numInt, $validated['note']);

            return response()->json(['ok' => true, 'msg' => 'Enregistré ✔']);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'msg' => 'Échec de l’enregistrement'], 422);
        } catch (\Throwable $e) {
            \Log::error('updateInternalNote failed', [
                'numInt' => $numInt,
                'ex' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'msg' => 'Échec de l’enregistrement'], 500);
        }
    }


}
