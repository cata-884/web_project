<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * BookingsController — API REST pentru rezervari la locuri de camping.
 *
 * Utilizatorii pot crea, vizualiza si anula propriile rezervari.
 * Ownerul campingului sau un admin poate confirma/completa rezervari.
 */
class BookingsController extends Controller
{
    /**
     * GET /api/bookings
     * Listeaza rezervarile userului curent (cu detalii camping).
     */
    #[NoReturn]
    public function index(): void
    {
        $user   = $this->requireAuth();
        $limit  = (int)($_GET['limit']  ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $bookings = $this->model->findByUserId((int)$user['id'], $limit, $offset);
        $total    = $this->model->countByUserId((int)$user['id']);

        $this->json([
            'bookings' => $bookings,
            'total'    => $total,
        ]);
    }

    /**
     * GET /api/bookings/{id}
     * Detaliile unei rezervari proprii.
     */
    #[NoReturn]
    public function show(int $id): void
    {
        $user = $this->requireAuth();
        $booking = $this->model->findById($id);

        if (!$booking) {
            $this->json(['error' => 'Rezervare inexistenta'], 404);
        }

        // Doar proprietarul rezervarii, owner-ul campingului sau admin
        $this->assertCanView($booking, $user);

        $this->json(['booking' => $booking]);
    }

    /**
     * POST /api/bookings
     * Creeaza o rezervare noua. Body: { camping_id, check_in, check_out, guests }
     */
    #[NoReturn]
    public function store(): void
    {
        $user = $this->requireAuth();
        $body = $this->getJsonBody();

        $campingId = (int)($body['camping_id'] ?? 0);
        $checkIn   = trim($body['check_in']    ?? '');
        $checkOut  = trim($body['check_out']   ?? '');
        $guests    = (int)($body['guests']     ?? 1);

        if ($campingId <= 0) {
            $this->json(['error' => 'camping_id obligatoriu'], 400);
        }
        if (!$checkIn || !$checkOut) {
            $this->json(['error' => 'check_in si check_out obligatorii (YYYY-MM-DD)'], 400);
        }

        $dateIn  = DateTime::createFromFormat('Y-m-d', $checkIn);
        $dateOut = DateTime::createFromFormat('Y-m-d', $checkOut);
        if (!$dateIn || !$dateOut) {
            $this->json(['error' => 'Format date invalid (YYYY-MM-DD)'], 400);
        }
        if ($dateOut <= $dateIn) {
            $this->json(['error' => 'check_out trebuie sa fie dupa check_in'], 400);
        }
        if ($dateIn < new DateTime('today')) {
            $this->json(['error' => 'Nu poti rezerva in trecut'], 400);
        }

        if ($guests < 1) {
            $this->json(['error' => 'Minim 1 guest'], 400);
        }

        $campingModel = new CampingsModel();
        $camping = $campingModel->findById($campingId);
        if (!$camping) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }

        if ($camping['capacity'] && $guests > (int)$camping['capacity']) {
            $this->json(['error' => 'Capacitate depasita (max: ' . $camping['capacity'] . ')'], 400);
        }
        if (!$this->model->checkAvailability($campingId, $checkIn, $checkOut)) {
            $this->json(['error' => 'Campingul nu este disponibil in perioada selectata'], 409);
        }

        $totalPrice = null;
        if ($camping['price_per_night']) {
            $totalPrice = $this->model->calculatePrice(
                (float)$camping['price_per_night'],
                $checkIn,
                $checkOut,
                $guests
            );
        }

        $id = $this->model->create((int)$user['id'], $campingId, [
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'guests'      => $guests,
            'total_price' => $totalPrice,
        ]);

        $this->json(['booking' => $this->model->findById($id)], 201);
    }

    /**
     * PATCH /api/bookings/{id}
     * Actualizeaza o rezervare. Body: { status?, check_in?, check_out?, guests? }
     */
    #[NoReturn]
    public function update(int $id): void
    {
        $user    = $this->requireAuth();
        $booking = $this->model->findById($id);

        if (!$booking) {
            $this->json(['error' => 'Rezervare inexistenta'], 404);
        }

        $body = $this->getJsonBody();
        if (!$body) {
            $this->json(['error' => 'Body gol'], 400);
        }

        if (isset($body['status'])) {
            $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
            if (!in_array($body['status'], $validStatuses, true)) {
                $this->json(['error' => 'Status invalid'], 400);
            }

            if (in_array($body['status'], ['confirmed', 'completed'], true)) {
                $campingModel = new CampingsModel();
                $campingOwnerId = $campingModel->getOwnerId((int)$booking['camping_id']);
                $isAdmin = ($user['role'] ?? 'user') === 'admin';
                $isCampingOwner = (int)$user['id'] === $campingOwnerId;

                if (!$isAdmin && !$isCampingOwner) {
                    $this->json(['error' => 'Doar owner-ul campingului poate confirma/completa'], 403);
                }
            }

            if ($body['status'] === 'cancelled') {
                $isOwner = (int)$user['id'] === (int)$booking['user_id'];
                $isAdmin = ($user['role'] ?? 'user') === 'admin';
                if (!$isOwner && !$isAdmin) {
                    $this->json(['error' => 'Nu poti anula aceasta rezervare'], 403);
                }
            }
        }

        $this->model->update($id, $body);
        $this->json(['booking' => $this->model->findById($id)]);
    }

    /**
     * POST /api/bookings/{id}/cancel
     * Shortcut: anuleaza o rezervare proprie.
     */
    #[NoReturn]
    public function cancel(int $id): void
    {
        $user = $this->requireAuth();

        $booking = $this->model->findById($id);
        if (!$booking) {
            $this->json(['error' => 'Rezervare inexistenta'], 404);
        }

        $isOwner = (int)$user['id'] === (int)$booking['user_id'];
        $isAdmin = ($user['role'] ?? 'user') === 'admin';
        if (!$isOwner && !$isAdmin) {
            $this->json(['error' => 'Nu poti anula aceasta rezervare'], 403);
        }

        if ($booking['status'] === 'cancelled') {
            $this->json(['error' => 'Rezervarea este deja anulata'], 400);
        }
        if ($booking['status'] === 'completed') {
            $this->json(['error' => 'Nu poti anula o rezervare finalizata'], 400);
        }

        $this->model->updateStatus($id, 'cancelled');
        $this->json(['ok' => true, 'booking' => $this->model->findById($id)]);
    }

    /**
     * GET /api/campings/{campingId}/availability
     * Verifica disponibilitatea unui camping. Query: ?check_in=...&check_out=...
     */
    #[NoReturn]
    public function availability(int $campingId): void
    {
        $checkIn  = trim($_GET['check_in']  ?? '');
        $checkOut = trim($_GET['check_out'] ?? '');

        if (!$checkIn || !$checkOut) {
            $this->json(['error' => 'check_in si check_out obligatorii'], 400);
        }

        $available = $this->model->checkAvailability($campingId, $checkIn, $checkOut);
        $this->json(['available' => $available]);
    }

    /**
     * Verifica daca user-ul poate vedea rezervarea.
     */
    private function assertCanView(array $booking, array $user): void
    {
        $isBookingOwner = (int)$user['id'] === (int)$booking['user_id'];
        $isAdmin = ($user['role'] ?? 'user') === 'admin';

        if ($isBookingOwner || $isAdmin) return;

        $campingModel = new CampingsModel();
        $campingOwnerId = $campingModel->getOwnerId((int)$booking['camping_id']);
        if ((int)$user['id'] === $campingOwnerId) return;

        $this->json(['error' => 'Nu ai acces la aceasta rezervare'], 403);
    }
}
