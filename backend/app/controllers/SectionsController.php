<?php

use JetBrains\PhpStorm\NoReturn;
class SectionsController extends Controller
{
    /**
     * GET /api/sections
     */
    #[NoReturn]
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->json([
            'sections' => $this->model->findByUserId((int)$user['id'])
        ]);
    }

    /**
     * POST /api/sections
     */
    #[NoReturn]
    public function store(): void
    {
        $user = $this->requireAuth();
        $body = $this->getJsonBody();

        $name = trim($body['name'] ?? '');
        if (strlen($name) < 1 || strlen($name) > 100) {
            $this->json(['error' => 'Numele sectiunii trebuie sa aiba 1-100 caractere'], 400);
        }

        $color = $body['color'] ?? '#4A90D9';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#4A90D9';
        }

        $id = $this->model->create((int)$user['id'], $name, $color);
        $this->json(['section' => $this->model->findById($id)], 201);
    }

    /**
     * GET /api/sections/{id}
     */
    #[NoReturn]
    public function show(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertOwnership($id, $user);

        $section = $this->model->findById($id);
        $this->json(['section' => $section]);
    }

    /**
     * PATCH /api/sections/{id}
     */
    #[NoReturn]
    public function update(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertOwnership($id, $user);

        $body = $this->getJsonBody();
        if (!$body) {
            $this->json(['error' => 'Body gol'], 400);
        }

        if (isset($body['name'])) {
            $name = trim($body['name']);
            if (strlen($name) < 1 || strlen($name) > 100) {
                $this->json(['error' => 'Numele sectiunii trebuie sa aiba 1-100 caractere'], 400);
            }
            $body['name'] = $name;
        }
        if (isset($body['color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $body['color'])) {
            unset($body['color']);
        }

        $this->model->update($id, $body);
        $this->json(['section' => $this->model->findById($id)]);
    }

    /**
     * DELETE /api/sections/{id}
     */
    #[NoReturn]
    public function destroy(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertOwnership($id, $user);

        $this->model->delete($id);
        $this->json(['ok' => true]);
    }

    /**
     * GET /api/sections/{id}/campings
     */
    #[NoReturn]
    public function campings(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertOwnership($id, $user);

        $this->json([
            'campings' => $this->model->getCampings($id)
        ]);
    }

    /**
     * POST /api/sections/{id}/campings
     */
    #[NoReturn]
    public function addCamping(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertOwnership($id, $user);

        $body = $this->getJsonBody();
        $campingId = (int)($body['camping_id'] ?? 0);
        if ($campingId <= 0) {
            $this->json(['error' => 'camping_id obligatoriu'], 400);
        }

        $campingModel = new CampingsModel();
        if (!$campingModel->findById($campingId)) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }

        $this->model->addCamping($id, $campingId);
        $this->json(['ok' => true, 'campings' => $this->model->getCampings($id)], 201);
    }

    /**
     * DELETE /api/sections/{id}/campings/{campingId}
     */
    #[NoReturn]
    public function removeCamping(int $id, int $campingId): void
    {
        $user = $this->requireAuth();
        $this->assertOwnership($id, $user);

        $this->model->removeCamping($id, $campingId);
        $this->json(['ok' => true]);
    }

    /**
     * Verifica ca sectiunea exista si apartine userului curent.
     */
    private function assertOwnership(int $sectionId, array $user): void
    {
        $section = $this->model->findById($sectionId);
        if (!$section) {
            $this->json(['error' => 'Sectiune inexistenta'], 404);
        }
        if (!$this->model->isOwner($sectionId, (int)$user['id'])) {
            $this->json(['error' => 'Nu ai acces la aceasta sectiune'], 403);
        }
    }
}
