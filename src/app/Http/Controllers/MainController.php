<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Services\InterventionService;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{
    protected $authService;
    protected $interventionService;

    public function __construct(AuthService $authService, InterventionService $interventionService)
    {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
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
        $interv = DB::table('t_intervention')
            ->selectRaw('t_intervention.*,
                     CAST(CommentInterne AS CHAR CHARACTER SET utf8mb4) AS CommentInterneTxt')
            ->where('NumInt', $numInt)
            ->first();

        if (!$interv) {
            return redirect()->route('accueil', ['id' => session('id')])->with('error', 'Intervention introuvable.');
        }

        $suivis = DB::table('t_suiviclient_histo')
            ->where('NumInt', $numInt)
            ->orderByDesc('CreatedAt')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('interventions.edit', compact('interv','suivis'));
    }

    public function updateInternalNote(Request $request, $numInt)
    {
        try {
            // 1) validation
            $request->validate([
                'id'   => ['required','string'],
                'note' => ['nullable','string','max:5000'],
            ]);

            // 2) garde-fou existence
            $exists = DB::table('t_intervention')->where('NumInt', $numInt)->exists();
            if (!$exists) {
                return response()->json(['ok'=>false,'msg'=>'Intervention introuvable'], 404);
            }

            // 3) update (BLOB accepté ; texte aussi). Si BLOB pose souci, on verra étape 3 ci-dessous
            DB::table('t_intervention')
                ->where('NumInt', $numInt)
                ->update(['CommentInterne' => $request->input('note')]);

            // 4) réponse JSON explicite UTF-8
            return response()->json(['ok'=>true], 200, ['Content-Type' => 'application/json; charset=utf-8']);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            // erreurs de validation → 422
            return response()->json([
                'ok'  => false,
                'msg' => $ve->getMessage(),
                'err' => $ve->errors(),
            ], 422);

        } catch (\Throwable $e) {
            // logge exactement ce qui casse
            \Log::error('updateInternalNote failed', [
                'numInt' => $numInt,
                'ex'     => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            // renvoie message lisible au JS
            return response()->json([
                'ok'  => false,
                'msg' => $e->getMessage(), // temporaire pour debug
            ], 500);
        }
    }

}
