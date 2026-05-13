<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * ReviewsController — API REST pentru recenzii la locuri de camping.
 *
 * Recenziile sunt legate de un camping. Fiecare user poate lasa maxim
 * o recenzie per camping. Doar autorul sau un admin poate edita/sterge
 */
class ReviewsController extends Controller
{
    /**
     * GET /api/campings/{campingId}/reviews
     * Listeaza recenziile unui camping (cu paginare)
     */
    #[NoReturn]
    public function index(int $campingId): void
    {
        $limit  = (int)($_GET['limit']  ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $reviews = $this->model->findByCampingId($campingId, $limit, $offset);
        $total   = $this->model->countByCampingId($campingId);

        $this->json([
            'reviews' => $reviews,
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
        ]);
    }

    /**
     * POST /api/campings/{campingId}/reviews
     * Adauga o recenzie. Body: { rating, title?, content? }
     */
    #[NoReturn]
    public function store(int $campingId): void
    {
        $user = $this->requireAuth();
        $body = $this->getJsonBody();

        // Validare rating
        $rating = (int)($body['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $this->json(['error' => 'Rating obligatoriu, intre 1 si 5'], 400);
        }

        $campingModel = new CampingsModel();
        if (!$campingModel->findById($campingId)) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }

        // Verificare duplicate (un user = o recenzie per camping)
        if ($this->model->userAlreadyReviewed((int)$user['id'], $campingId)) {
            $this->json(['error' => 'Ai recenzat deja acest camping'], 409);
        }

        $body['rating'] = $rating;
        if (isset($body['title'])) {
            $body['title'] = htmlspecialchars(trim($body['title']), ENT_QUOTES, 'UTF-8');
        }
        if (isset($body['content'])) {
            $body['content'] = htmlspecialchars(trim($body['content']), ENT_QUOTES, 'UTF-8');
        }

        try {
            $id = $this->model->create((int)$user['id'], $campingId, $body);
            $this->json(['review' => $this->model->findById($id)], 201);
        } catch (Exception $e) {
            $this->json(['error' => 'Eroare la creare: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/reviews/{id}
     * Detaliile unei recenzii.
     */
    #[NoReturn]
    public function show(int $id): void
    {
        $review = $this->model->findById($id);
        if (!$review) {
            $this->json(['error' => 'Recenzie inexistentă'], 404);
        }
        $this->json(['review' => $review]);
    }

    /**
     * PATCH /api/reviews/{id}
     * Editeaza propria recenzie. Body: { rating?, title?, content? }
     */
    #[NoReturn]
    public function update(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertCanModify($id, $user);

        $body = $this->getJsonBody();
        if (!$body) {
            $this->json(['error' => 'Body gol'], 400);
        }

        if (isset($body['rating'])) {
            $body['rating'] = (int)$body['rating'];
            if ($body['rating'] < 1 || $body['rating'] > 5) {
                $this->json(['error' => 'Rating intre 1 si 5'], 400);
            }
        }

        if (isset($body['title'])) {
            $body['title'] = htmlspecialchars(trim($body['title']), ENT_QUOTES, 'UTF-8');
        }
        if (isset($body['content'])) {
            $body['content'] = htmlspecialchars(trim($body['content']), ENT_QUOTES, 'UTF-8');
        }

        $this->model->update($id, $body);
        $this->json(['review' => $this->model->findById($id)]);
    }

    /**
     * DELETE /api/reviews/{id}
     * Sterge propria recenzie (sau orice recenzie daca admin)
     */
    #[NoReturn]
    public function destroy(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertCanModify($id, $user);

        $this->model->delete($id);
        $this->json(['ok' => true]);
    }

    /**
     * Verifică că recenzia există si că userul are dreptul să o modifice
     */
    private function assertCanModify(int $reviewId, array $user): void
    {
        $ownerId = $this->model->getOwnerId($reviewId);
        if ($ownerId === null) {
            $this->json(['error' => 'Recenzie inexistentă'], 404);
        }
        if (($user['role'] ?? 'user') === 'admin') return;
        if ((int)$user['id'] === $ownerId) return;

        $this->json(['error' => 'Nu ai drepturi pe aceasta recenzie'], 403);
    }
}
