<?php

use App\Http\Controllers\MainController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('authentification');
});

Route::get('/authentification', [MainController::class, 'showLoginForm'])
    ->name('authentification');

Route::post('/authentification', [MainController::class, 'login'])->name('authentification.post');


Route::get('/deconnexion', function () {
    session()->flush(); // supprime toutes les données de session
    return redirect()->route('authentification');
})->name('deconnexion');

Route::get('/accueil', function () {
    return view('accueil')->with('id', session('id'));
})->name('accueil')->middleware('check.session');

Route::get('/erreur', function () {
    $message = session('message', 'Une erreur est survenue.');
    return view('error', ['message' => $message]);
})->name('erreur');

