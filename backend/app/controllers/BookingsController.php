<?php
use JetBrains\PhpStorm\NoReturn;

class BookingsController extends Controller
{
    private BookingsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new BookingsService(new BookingsRepository(), new CampingsRepository());
    }

    #[NoReturn]
    public function index(): void
    {
        $user = $this->requireAuth();
        $res  = $this->service->listForUser(
            (int)$user['id'],
            (int)($_GET['limit']  ?? 20),
            (int)($_GET['offset'] ?? 0),
            isset($_GET['camping_id']) ? (int)$_GET['camping_id'] : null,
        );
        $this->json([
            'bookings' => array_map([BookingDTO::class, 'fromRow'], $res['items']),
            'total'    => $res['total'],
        ]);
    }

    #[NoReturn]
    public function store(): void
    {
        $user    = $this->requireAuth();
        $booking = $this->service->create((int)$user['id'], $this->getJsonBody());
        $this->json(['booking' => BookingDTO::fromRow($booking)], 201);
    }

}
