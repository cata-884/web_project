<?php
use JetBrains\PhpStorm\NoReturn;

class CampingsController extends Controller
{
    private CampingsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CampingsService(new CampingsRepository());
    }

    #[NoReturn]
    public function mine(): void
    {
        $user = $this->requireAuth();
        $this->json(['campings' => array_map([CampingDTO::class, 'fromMine'], $this->service->listMine((int)$user['id']))]);
    }

    #[NoReturn]
    public function index(): void
    {
        $res = $this->service->search($_GET);
        $this->json([
            'campings' => array_map([CampingDTO::class, 'fromRowList'], $res['campings']),
            'total'    => $res['total'],
            'limit'    => $res['limit'],
            'offset'   => $res['offset'],
        ]);
    }

    #[NoReturn]
    public function show(int $id): void
    {
        $this->json(['camping' => CampingDTO::fromRow($this->service->getById($id))]);
    }

    #[NoReturn]
    public function showBySlug(string $slug): void
    {
        $this->json(['camping' => CampingDTO::fromRow($this->service->getBySlug($slug))]);
    }

    #[NoReturn]
    public function mapMarkers(): void
    {
        if (empty($_GET['bbox'])) throw new ValidationException('bbox obligatoriu: south,west,north,east');
        $this->json(['markers' => $this->service->mapMarkers($_GET['bbox'])]);
    }

    #[NoReturn]
    public function store(): void
    {
        $user    = $this->requireOrganizer();
        $camping = $this->service->create((int)$user['id'], $this->getJsonBody());
        $this->json(['camping' => CampingDTO::fromRow($camping)], 201);
    }

    #[NoReturn]
    public function update(int $id): void
    {
        $user    = $this->requireAuth();
        $camping = $this->service->update($id, $this->getJsonBody(), $user);
        $this->json(['camping' => CampingDTO::fromRow($camping)]);
    }

    #[NoReturn]
    public function destroy(int $id): void
    {
        $user = $this->requireAuth();
        $this->service->delete($id, $user);
        $this->json(['ok' => true]);
    }

    #[NoReturn]
    public function resubmit(int $id): void
    {
        $user = $this->requireAuth();
        $this->service->resubmit($id, $user);
        $this->json(['ok' => true, 'message' => 'Cererea a fost retrimisa spre aprobare.']);
    }
}
