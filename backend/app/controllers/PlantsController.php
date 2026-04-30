<?php
class PlantsController extends Controller
{
    public function index(): void
    {
        // TODO: lista plante (cu filtre/search)
    }

    public function show(int $id): void
    {
        // TODO
    }

    public function create(): void
    {
        $this->requireAuth();
        // TODO: formular adaugare
    }

    public function store(): void
    {
        $this->requireAuth();
        // TODO: insert planta, redirect show
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        // TODO: verifica permisiuni, formular editare
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        // TODO
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();
        // TODO: verifica permisiuni
    }

    // --- Endpoint-uri Ajax ---

    public function apiSearch(): void
    {
        // TODO: search multi-criterial, returneaza JSON
        $this->json(['plants' => []]);
    }

    public function apiExternal(): void
    {
        // TODO: cauta in API extern (Trefle / GBIF), returneaza JSON
        $this->json(['results' => []]);
    }
}
