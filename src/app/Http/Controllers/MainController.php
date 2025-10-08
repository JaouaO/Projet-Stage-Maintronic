<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use Illuminate\Http\Request;
use App\Services\AuthService;

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

}
