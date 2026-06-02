<?php
use JetBrains\PhpStorm\NoReturn;

class PreferencesController extends Controller
{
    private PreferencesService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PreferencesService(new PreferencesRepository());
    }

    #[NoReturn]
    public function get(): void
    {
        $user = $this->requireAuth();
        $this->json($this->service->get((int)$user['id']));
    }

    #[NoReturn]
    public function save(): void
    {
        $user = $this->requireAuth();
        $this->service->save((int)$user['id'], $this->getJsonBody());
        $this->json(['ok' => true]);
    }
}
