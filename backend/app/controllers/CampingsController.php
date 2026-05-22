<?php

use JetBrains\PhpStorm\NoReturn;

class CampingsController extends Controller
{
    #[NoReturn]
    public function index(): void
    {
        $filters = [
            'region'    => $_GET['region']    ?? null,
            'type'      => $_GET['type']      ?? null,
            'min_price' => $_GET['min_price'] ?? null,
            'max_price' => $_GET['max_price'] ?? null,
            'min_rating'=> $_GET['min_rating']?? null,
            'search'    => $_GET['search']    ?? null,
            'limit'     => (int)($_GET['limit'] ?? 20),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];
        $campings = $this->model->search($filters);
        $total    = $this->model->countSearch($filters);

        $this->json([
            'campings' => $campings,
            'total'    => $total,
            'limit'    => $filters['limit'],
            'offset'   => $filters['offset']
        ]);
    }

    #[NoReturn]
    public function show(int $id): void
    {
        $camping = $this->model->findById($id);
        if (!$camping) $this->json(['error' => 'Camping inexistent'], 404);
        $this->json(['camping' => $camping]);
    }

    #[NoReturn]
    public function mapMarkers(): void
    {
        // bbox=south,west,north,east
        $bbox = $_GET['bbox'] ?? null;
        if (!$bbox) $this->json(['error' => 'bbox obligatoriu: south,west,north,east'], 400);

        $parts = array_map('floatval', explode(',', $bbox));
        if (count($parts) !== 4) $this->json(['error' => 'bbox invalid'], 400);

        [$s, $w, $n, $e] = $parts;
        $this->json(['markers' => $this->model->findInBbox($s, $w, $n, $e)]);
    }

    #[NoReturn]
    public function store(): void
    {
        // Doar organizer sau admin pot crea campinguri
        $user = $this->requireOrganizer();
        $body = $this->getJsonBody();

        // minimum validation
        if (empty($body['name']) || strlen($body['name']) < 3 || strlen($body['name']) > 200) {
            $this->json(['error' => 'name obligatoriu (3-200 caractere)'], 400);
        }
        if (!isset($body['latitude'], $body['longitude'])) {
            $this->json(['error' => 'latitude si longitude obligatorii'], 400);
        }
        if ($body['latitude']  < -90  || $body['latitude']  > 90)  $this->json(['error' => 'latitude invalid'], 400);
        if ($body['longitude'] < -180 || $body['longitude'] > 180) $this->json(['error' => 'longitude invalid'], 400);

        $validTypes = ['wild','glamping','rv','tent','cabin'];
        if (!empty($body['type']) && !in_array($body['type'], $validTypes, true)) {
            $this->json(['error' => 'type invalid; valori: '.implode(', ', $validTypes)], 400);
        }

        $id = $this->model->create((int)$user['id'], $body);
        $this->json(['camping' => $this->model->findById($id)], 201);
    }

    #[NoReturn]
    public function update(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertCanModify($id, $user);

        $body = $this->getJsonBody();
        if (!$body) $this->json(['error' => 'Body gol'], 400);

        $this->model->update($id, $body);
        $this->json(['camping' => $this->model->findById($id)]);
    }

    #[NoReturn]
    public function destroy(int $id): void
    {
        $user = $this->requireAuth();
        $this->assertCanModify($id, $user);

        $this->model->delete($id);
        $this->json(['ok' => true]);
    }

    /**
     * Owner-ul camping-ului sau adminii pot modifica/sterge campingul.
     */
    private function assertCanModify(int $campingId, array $user): void
    {
        $ownerId = $this->model->getOwnerId($campingId);
        if ($ownerId === null) $this->json(['error' => 'Camping inexistent'], 404);

        if (($user['role'] ?? 'user') === 'admin') return;
        if ((int)$user['id'] === $ownerId)        return;

        $this->json(['error' => 'Nu ai drepturi pe acest camping'], 403);
    }
}