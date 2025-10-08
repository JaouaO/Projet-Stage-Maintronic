<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{
    protected  $authService;
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
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

        $result =$this->authService->verifHoraires($codeSal);

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

    public function showInterventions()
    {
        return view('interventions.show');
    }

    public function entree(NotBlankRequest $request)
    {
        $idFromUrl  = $request->query('id'); // conservé par ton middleware
        $sessionId  = session('id');
        if (!$sessionId || $idFromUrl !== $sessionId) {
            return redirect()->route('authentification')->with('message', 'Session invalide.');
        }

        // Validation
        $validated = $request->validate([
            'num_int' => ['required','regex:/^[A-Za-z0-9_-]+$/','exists:t_intervention,NumInt'],
            'agence'  => ['required','regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        // Ici, si tout est OK → page A
        return redirect()->route('interv.edit', ['numInt' => $validated['num_int']]);
    }

    public function editIntervention($numInt)
    {
        // Exemple minimal : affiche la fiche
        $interv = DB::table('t_intervention')->where('NumInt', $numInt)->first();
        if (!$interv) {
            return redirect()
                ->route('accueil', ['id' => session('id')])
                ->with('error', 'Une erreur est survenue dans la saisie. Veuillez vérifier vos informations.');
        }
        return view('interventions.edit', compact('interv'));
    }


}
