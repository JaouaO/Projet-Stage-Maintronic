<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\UpdateInterventionRequest;
use App\Services\AccessInterventionService;
use App\Services\DTO\RdvTemporaireDTO;
use App\Services\DTO\UpdateInterventionDTO;
use App\Services\InterventionHistoryService;
use App\Services\InterventionService;
use App\Services\PlanningService;
use App\Services\TraitementDossierService;
use App\Services\UpdateInterventionService;
use App\Services\Write\PlanningWriteService;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class MainController extends Controller
{
    protected AuthService $authService;
    protected InterventionService $interventionService;
    private TraitementDossierService $traitementDossierService;
    private PlanningService $planningService;
    private PlanningWriteService $planningWriteService;

    private UpdateInterventionService $updateInterventionService;
    private AccessInterventionService $accessInterventionService;
    private InterventionHistoryService $historyService;

    public function __construct(
        AuthService               $authService,
        InterventionService       $interventionService,
        TraitementDossierService  $traitementDossierService,
        PlanningService           $planningService,
        PlanningWriteService      $planningWriteService,
        UpdateInterventionService $updateInterventionService,
        AccessInterventionService $accessInterventionService,
        InterventionHistoryService $historyService
    )
    {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
        $this->traitementDossierService = $traitementDossierService;
        $this->planningService = $planningService;
        $this->planningWriteService = $planningWriteService;
        $this->updateInterventionService = $updateInterventionService;
        $this->accessInterventionService = $accessInterventionService;
        $this->historyService = $historyService;
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
            'id' => $id,
            'codeSal' => $login['user']['codeSal'],
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

    // MainController@showInterventions
    public function showInterventions(Request $request)
    {

        Log::info('[PING] updateAndPlanRdv atteint');
        $perPage = (int)$request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 10;

        $agencesAutorisees = (array) session('agences_autorisees', []);
        $codeSal           = (string) session('codeSal', '');

        $rows = $this->interventionService->listPaginatedSimple($perPage, $agencesAutorisees, $codeSal);

        $todoTagClass = [
            // On garde vos classes de couleurs si vous voulez réutiliser l’affichage des tags.
            'CONFIRMER_RDV' => 'blue',
            'PLANIFIER_RDV' => 'amber',
            'CLOTURER'      => 'green',
            'DIAGNOSTIC'    => 'violet',
        ];

        return view('interventions.show', [
            'rows'          => $rows,
            'todoTagClass'  => $todoTagClass,
            'perPage'       => $perPage,
        ]);
    }
    public function history(Request $request, string $numInt): \Illuminate\Http\Response
    {
        // Récupération à la demande (service dédié)
        $suivis = $this->historyService->fetchHistory($numInt);

        // Si tu préfères renvoyer une page HTML autonome (parfait pour window.open)
        // crée la vue resources/views/interventions/history_popup.blade.php
        if (View::exists('interventions.history_popup')) {
            return response()->view('interventions.history_popup', [
                'numInt' => $numInt,
                'suivis' => $suivis,
            ]);
        }

        // Fallback minimal (au cas où la vue n’est pas encore créée)
        return response()->view('interventions.history_fallback', [
            'numInt' => $numInt,
            'suivis' => $suivis,
        ]);
    }



    public function entree(NotBlankRequest $request)
    {
        $idFromUrl = $request->query('id');
        $sessionId = session('id');
        if (!$sessionId || $idFromUrl !== $sessionId) {
            return redirect()->route('authentification')->with('message', 'Session invalide.');
        }

        $validated = $request->validate([
            'num_int' => ['required', 'regex:/^[A-Za-z0-9_-]+$/', 'exists:t_intervention,NumInt'],
            'agence' => ['required', 'regex:/^[A-Za-z0-9_-]+$/'],
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

        // ↓ Liste unifiée des personnes sélectionnables pour ce dossier
        $people = $this->accessInterventionService->listPeopleForNumInt($numInt);

        // vous pouvez passer $people à vos partials au lieu de techniciens/salaries
        return view('interventions.edit', $payload + [
                'people' => $people, // Collection de {CodeSal, NomSal, CodeAgSal, access_level}
            ]);
    }

    /** API planning */
    public function apiPlanningTech(Request $request, $codeTech): \Illuminate\Http\JsonResponse
    {
        try {
            $payload = $this->planningService->getPlanning(
                $codeTech,
                $request->query('from'),
                $request->query('to'),
                (int)$request->query('days', 5),
                'Europe/Paris'
            );
            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('apiPlanningTech error', ['ex' => $e->getMessage()]);
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

    public function updateIntervention(UpdateInterventionRequest $request, $numInt): \Illuminate\Http\RedirectResponse
    {

        Log::debug('urgent?', ['raw' => $request->input('urgent'), 'bool' => $request->boolean('urgent')]);
        $dto = UpdateInterventionDTO::fromRequest($request, (string)$numInt);

        try {
            $this->updateInterventionService->updateAndPlanRdv($dto);
            return redirect()->route('interventions.edit', $dto->numInt)->with('ok', 'Mise à jour effectuée.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['global' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['global' => 'Une erreur est survenue.'])->withInput();
        }
    }

    public function rdvTemporaire(Request $request, string $numInt): \Illuminate\Http\JsonResponse
    {

        try {
            $codeSalAuteur = session('codeSal') ?: null;
            $dto = RdvTemporaireDTO::fromRequest($request, $numInt, $codeSalAuteur);

            $mode = $this->updateInterventionService->ajoutRdvTemporaire($dto);

            return response()->json(['ok' => true, 'mode' => $mode]);

        } catch (\Illuminate\Database\QueryException $qe) {
            $ei = $qe->errorInfo ?? [];
            return response()->json([
                'ok' => false,
                'type' => 'QueryException',
                'sqlstate' => $ei[0] ?? null,
                'errno' => $ei[1] ?? null,
                'errmsg' => $ei[2] ?? $qe->getMessage(),
                'file' => basename($qe->getFile()) . ':' . $qe->getLine(),
            ], 500);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'errors' => $ve->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'type' => 'Throwable',
                'errmsg' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }


    public function rdvTempCheck(Request $request, string $numInt): \Illuminate\Http\JsonResponse
    {
        try {
            $exclude = $request->input('exclude');
            $rows = $this->planningService->listTempsByNumInt($numInt);

            if (is_array($exclude)
                && !empty($exclude['codeTech'])
                && !empty($exclude['startDate'])
                && !empty($exclude['startTime'])) {

                $rows = $rows->reject(function ($r) use ($exclude) {
                    return ($r->CodeTech === $exclude['codeTech'])
                        && ($r->StartDate === $exclude['startDate'])
                        && ($r->StartTime === $exclude['startTime']);
                });
            }
            return response()->json([
                'ok' => true,
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
            $deleted = $this->planningWriteService->purgeTempsByNumInt($numInt);
            return response()->json(['ok' => true, 'deleted' => $deleted]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    public function rdvTempDelete(Request $request, string $numInt, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $deleted = $this->planningWriteService->deleteTempById($numInt, $id);

            if ($deleted === 0) {
                return response()->json([
                    'ok' => false,
                    'msg' => "Aucun RDV temporaire trouvé (ou déjà validé) pour ce dossier.",
                ], 404);
            }

            return response()->json(['ok' => true, 'deleted' => 1]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

}

