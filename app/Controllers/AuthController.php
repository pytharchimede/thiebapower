<?php
namespace App\Controllers;

use App\Core\App;
use App\Services\Auth;

final class AuthController
{
    public function loginPage(): void
    {
        Auth::start();
        if (Auth::user()) {
            App::redirect('/admin');
            return;
        }
        App::view('login', ['csrf' => $_SESSION['csrf'], 'error' => false]);
    }

    public function login(): void
    {
        Auth::start();
        if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Formulaire expiré');
        }
        if (Auth::login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            App::redirect('/admin');
            return;
        }
        http_response_code(401);
        App::view('login', ['csrf' => $_SESSION['csrf'], 'error' => true]);
    }

    public function logout(): void
    {
        Auth::start();
        if (!Auth::user() || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Formulaire expiré');
        }
        Auth::logout();
        App::redirect('/admin/login');
    }
}
