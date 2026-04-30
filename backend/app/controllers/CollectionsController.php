<?php
class CollectionsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        // TODO: lista colectiile userului (proprii + cele unde e membru)
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        // TODO: verifica acces (owner/member/public), afiseaza colectia
    }

    public function create(): void
    {
        $this->requireAuth();
        // TODO: formular colectie noua
    }

    public function store(): void
    {
        $this->requireAuth();
        // TODO
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        // TODO: doar owner
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        // TODO: doar owner
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();
        // TODO: doar owner
    }

    // Toggle public/privat
    public function togglePublish(int $id): void
    {
        $this->requireAuth();
        // TODO
    }

    // --- Membri si permisiuni ---

    public function members(int $id): void
    {
        $this->requireAuth();
        // TODO: lista membri + form invitatie
    }

    public function addMember(int $id): void
    {
        $this->requireAuth();
        // TODO: doar owner; rol editor/viewer
    }

    public function removeMember(int $id, int $userId): void
    {
        $this->requireAuth();
        // TODO
    }

    // --- Import / Export ---

    public function export(int $id): void
    {
        $this->requireAuth();
        // TODO: format=json|xml
    }

    public function import(int $id): void
    {
        $this->requireAuth();
        // TODO: doar owner/editor
    }
}
