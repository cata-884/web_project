<?php
class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        // TODO: profil + preferinte
    }

    public function updateProfile(): void
    {
        $this->requireAuth();
        // TODO: nume, email, avatar
    }

    public function updatePassword(): void
    {
        $this->requireAuth();
        // TODO: verifica parola veche, password_hash pentru cea noua
    }

    public function updatePreferences(): void
    {
        $this->requireAuth();
        // TODO: tema (light/dark/auto), limba, view default (galerie/tabel), sistem masuri (metric/imperial)
    }

    public function deleteAccount(): void
    {
        $this->requireAuth();
        // TODO: zona pericol - confirmare + cascade delete
    }
}
