<?php
use JetBrains\PhpStorm\NoReturn;

class ReviewsController extends Controller
{
    private ReviewsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ReviewsService(new ReviewsRepository(), new CampingsRepository());
    }

    #[NoReturn]
    public function index(int $campingId): void
    {
        $res = $this->service->listForCamping(
            $campingId,
            (int)($_GET['limit']  ?? 20),
            (int)($_GET['offset'] ?? 0),
        );
        $this->json([
            'reviews' => array_map([ReviewDTO::class, 'fromRow'], $res['items']),
            'total'   => $res['total'],
            'limit'   => $res['limit'],
            'offset'  => $res['offset'],
        ]);
    }

    #[NoReturn]
    public function store(int $campingId): void
    {
        $user   = $this->requireAuth();
        $review = $this->service->create((int)$user['id'], $campingId, $this->getJsonBody());
        $this->json(['review' => ReviewDTO::fromRow($review)], 201);
    }

    #[NoReturn]
    public function update(int $id): void
    {
        $user   = $this->requireAuth();
        $review = $this->service->update($id, $this->getJsonBody(), $user);
        $this->json(['review' => ReviewDTO::fromRow($review)]);
    }

    #[NoReturn]
    public function destroy(int $id): void
    {
        $user = $this->requireAuth();
        $this->service->delete($id, $user);
        $this->json(['ok' => true]);
    }
}
