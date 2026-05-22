<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * OrganizersController — API pentru cererile de promovare la organizer.
 *
 * User simplu poate aplica (submit cerere cu date legale).
 * Admin poate vedea cereri pending, aproba sau respinge.
 */
class OrganizersController extends Controller
{
    /**
     * POST /api/organizers/apply
     * Body: { legal_name, cui?, id_card_url?, authorization_url?, contract_url? }
     *
     * User simplu submitese cerere de promovare la organizer.
     */
    #[NoReturn]
    public function apply(): void
    {
        $user = $this->requireAuth();
        $body = $this->getJsonBody();

        // User-ul trebuie sa fie 'user' (nu deja organizer/admin)
        if (($user['role'] ?? 'user') !== 'user') {
            $this->json(['error' => 'Doar utilizatorii normali pot aplica'], 403);
        }

        // Validare legal_name
        $legalName = trim($body['legal_name'] ?? '');
        if (strlen($legalName) < 3 || strlen($legalName) > 200) {
            $this->json(['error' => 'legal_name: 3-200 caractere'], 400);
        }

        // Validare CUI (optional, dar daca e prezent, format romanesc simplu)
        $cui = isset($body['cui']) ? trim($body['cui']) : null;
        if ($cui !== null && $cui !== '') {
            // Format: optional "RO" + 2-10 cifre
            if (!preg_match('/^(RO)?\d{2,10}$/i', $cui)) {
                $this->json(['error' => 'CUI invalid (ex: RO12345678 sau 12345678)'], 400);
            }
        }

        // Exista deja o cerere pending sau approved?
        $existing = $this->model->findApplicationByUserId((int)$user['id']);
        if ($existing && in_array($existing['status'], ['pending', 'approved'], true)) {
            $this->json(['error' => 'Exista deja o cerere ' . $existing['status']], 409);
        }

        $id = $this->model->createApplication((int)$user['id'], [
            'legal_name'        => $legalName,
            'cui'               => $cui,
            'id_card_url'       => $body['id_card_url'] ?? null,
            'authorization_url' => $body['authorization_url'] ?? null,
            'contract_url'      => $body['contract_url'] ?? null,
        ]);

        $this->json(['application' => $this->model->findById($id)], 201);
    }

    /**
     * GET /api/organizers/my-application
     * Returneaza cererea proprie sau 404.
     */
    #[NoReturn]
    public function myApplication(): void
    {
        $user = $this->requireAuth();

        $application = $this->model->findApplicationByUserId((int)$user['id']);
        if (!$application) {
            $this->json(['error' => 'Nu ai nicio cerere de promovare'], 404);
        }

        $this->json(['application' => $application]);
    }

    /**
     * GET /api/organizers/pending
     * Admin only — lista cererilor pending (paginata).
     */
    #[NoReturn]
    public function pending(): void
    {
        $this->requireAdmin();

        $limit  = (int)($_GET['limit']  ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $applications = $this->model->findPendingApplications($limit, $offset);
        $total        = $this->model->countPending();

        $this->json([
            'applications' => $applications,
            'total'        => $total,
            'limit'        => $limit,
            'offset'       => $offset,
        ]);
    }

    /**
     * POST /api/organizers/{id}/approve
     * Admin only — aproba o cerere pending.
     */
    #[NoReturn]
    public function approve(int $id): void
    {
        $admin = $this->requireAdmin();

        $application = $this->model->findById($id);
        if (!$application) {
            $this->json(['error' => 'Cerere inexistenta'], 404);
        }

        if ($application['status'] !== 'pending') {
            $this->json(['error' => 'Cererea nu este in status pending (actual: ' . $application['status'] . ')'], 400);
        }

        $success = $this->model->approveApplication($id, (int)$admin['id']);
        if (!$success) {
            $this->json(['error' => 'Nu s-a putut aproba cererea'], 500);
        }

        $this->json([
            'ok'          => true,
            'message'     => 'Cerere aprobata. Userul a fost promovat la organizer.',
            'application' => $this->model->findById($id),
        ]);
    }

    /**
     * POST /api/organizers/{id}/reject
     * Admin only — respinge o cerere pending. Body: { notes }
     */
    #[NoReturn]
    public function reject(int $id): void
    {
        $admin = $this->requireAdmin();
        $body  = $this->getJsonBody();

        $application = $this->model->findById($id);
        if (!$application) {
            $this->json(['error' => 'Cerere inexistenta'], 404);
        }

        if ($application['status'] !== 'pending') {
            $this->json(['error' => 'Cererea nu este in status pending (actual: ' . $application['status'] . ')'], 400);
        }

        $notes = trim($body['notes'] ?? '');
        if (strlen($notes) < 3) {
            $this->json(['error' => 'Motivul respingerii (notes) obligatoriu, minim 3 caractere'], 400);
        }

        $success = $this->model->rejectApplication($id, (int)$admin['id'], $notes);
        if (!$success) {
            $this->json(['error' => 'Nu s-a putut respinge cererea'], 500);
        }

        $this->json([
            'ok'          => true,
            'message'     => 'Cerere respinsa.',
            'application' => $this->model->findById($id),
        ]);
    }
}
