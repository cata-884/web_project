<?php
class AuthController extends Controller
{
    public function loginForm(): void
    {
        // TODO
    }

    public function login(): void
    {
        // TODO: validare credentiale, set $_SESSION['user_id'], redirect dashboard
    }

    public function registerForm(): void
    {
        // TODO
    }

    public function register(): void
    {
        // TODO: password_hash, insert user, redirect login
    }

    public function logout(): void
    {
        // TODO: session_destroy, redirect /
    }
}
