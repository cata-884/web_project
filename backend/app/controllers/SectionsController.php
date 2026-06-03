<?php
use JetBrains\PhpStorm\NoReturn;

class SectionsController extends Controller
{
    private SectionsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new SectionsService(new SectionsRepository(), new CampingsRepository());
    }

    #[NoReturn]
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->json(['sections' => array_map([SectionDTO::class, 'fromRow'], $this->service->listForUser((int)$user['id']))]);
    }

    #[NoReturn]
    public function store(): void
    {
        $user    = $this->requireAuth();
        $section = $this->service->create((int)$user['id'], $this->getJsonBody());
        $this->json(['section' => SectionDTO::fromRow($section)], 201);
    }

    #[NoReturn]
    public function campings(int $id): void
    {
        $user = $this->requireAuth();
        $this->json(['campings' => array_map([CampingDTO::class, 'fromRowList'], $this->service->getCampings($id, $user))]);
    }

    #[NoReturn]
    public function addCamping(int $id): void
    {
        $user     = $this->requireAuth();
        $campings = $this->service->addCamping($id, $this->getJsonBody(), $user);
        $this->json(['ok' => true, 'campings' => array_map([CampingDTO::class, 'fromRowList'], $campings)], 201);
    }

    #[NoReturn]
    public function removeCamping(int $id, int $campingId): void
    {
        $user = $this->requireAuth();
        $this->service->removeCamping($id, $campingId, $user);
        $this->json(['ok' => true]);
    }
}
