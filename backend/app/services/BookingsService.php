<?php
class BookingsService
{
    public function __construct(
        private BookingsRepository $bookings,
        private CampingsRepository $campings,
    ) {}

    public function listForUser(int $userId, int $limit, int $offset, ?int $campingId): array
    {
        return [
            'items' => $this->bookings->findByUserId($userId, $limit, $offset, $campingId),
            'total' => $this->bookings->countByUserId($userId, $campingId),
        ];
    }

    public function getForUser(int $id, array $user): array
    {
        $booking = $this->bookings->findById($id);
        if (!$booking) throw new NotFoundException('Rezervare inexistenta');
        $this->assertCanView($booking, $user);
        return $booking;
    }

    public function create(int $userId, array $data): array
    {
        $campingId = (int)($data['camping_id'] ?? 0);
        $checkIn   = trim($data['check_in']    ?? '');
        $checkOut  = trim($data['check_out']   ?? '');
        $guests    = (int)($data['guests']     ?? 1);

        if ($campingId <= 0)              throw new ValidationException('camping_id obligatoriu');
        $in  = DateTime::createFromFormat('Y-m-d', $checkIn);
        $out = DateTime::createFromFormat('Y-m-d', $checkOut);
        if (!$in || !$out)               throw new ValidationException('Format date invalid (YYYY-MM-DD)');
        if ($out <= $in)                 throw new ValidationException('check_out trebuie sa fie dupa check_in');
        if ($in < new DateTime('today')) throw new ValidationException('Nu poti rezerva in trecut');
        if ($guests < 1)                 throw new ValidationException('Minim 1 guest');

        $camping = $this->campings->findById($campingId);
        if (!$camping) throw new NotFoundException('Camping inexistent');
        if ($camping['capacity'] && $guests > (int)$camping['capacity'])
            throw new ValidationException('Capacitate depasita (max: ' . $camping['capacity'] . ')');
        if (!$this->bookings->checkAvailability($campingId, $checkIn, $checkOut))
            throw new ConflictException('Campingul nu este disponibil in perioada selectata');

        $id = $this->bookings->create($userId, $campingId, [
            'check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => $guests,
        ]);
        return $this->bookings->findById($id);
    }

    public function update(int $id, array $body, array $user): array
    {
        $booking = $this->bookings->findById($id);
        if (!$booking) throw new NotFoundException('Rezervare inexistenta');
        if (!$body)    throw new ValidationException('Body gol');
        if (isset($body['status'])) $this->assertStatusChangeAllowed($booking, $body['status'], $user);
        $this->bookings->update($id, $body);
        return $this->bookings->findById($id);
    }

    public function cancel(int $id, array $user): array
    {
        $booking = $this->bookings->findById($id);
        if (!$booking) throw new NotFoundException('Rezervare inexistenta');
        $this->assertCanCancel($booking, $user);
        if ($booking['status'] === 'cancelled') throw new ValidationException('Rezervarea este deja anulata');
        if ($booking['status'] === 'completed') throw new ValidationException('Nu poti anula o rezervare finalizata');
        $this->bookings->updateStatus($id, 'cancelled');
        return $this->bookings->findById($id);
    }

    public function availability(int $campingId, string $checkIn, string $checkOut): bool
    {
        if (!$checkIn || !$checkOut) throw new ValidationException('check_in si check_out obligatorii');
        return $this->bookings->checkAvailability($campingId, $checkIn, $checkOut);
    }

    private function assertCanView(array $booking, array $user): void
    {
        if ($this->isBookingOwner($booking, $user) || $this->isAdmin($user)) return;
        if ((int)$user['id'] === $this->campings->getOwnerId((int)$booking['camping_id'])) return;
        throw new ForbiddenException('Nu ai acces la aceasta rezervare');
    }

    private function assertCanCancel(array $booking, array $user): void
    {
        if (!$this->isBookingOwner($booking, $user) && !$this->isAdmin($user))
            throw new ForbiddenException('Nu poti anula aceasta rezervare');
    }

    private function assertStatusChangeAllowed(array $booking, string $status, array $user): void
    {
        $valid = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($status, $valid, true)) throw new ValidationException('Status invalid');

        if (in_array($status, ['confirmed', 'completed'], true)) {
            $ownerId = $this->campings->getOwnerId((int)$booking['camping_id']);
            if (!$this->isAdmin($user) && (int)$user['id'] !== $ownerId)
                throw new ForbiddenException('Doar owner-ul campingului poate confirma/completa');
        }
        if ($status === 'cancelled' && !$this->isBookingOwner($booking, $user) && !$this->isAdmin($user))
            throw new ForbiddenException('Nu poti anula aceasta rezervare');
    }

    private function isBookingOwner(array $b, array $u): bool { return (int)$u['id'] === (int)$b['user_id']; }
    private function isAdmin(array $u): bool { return ($u['role'] ?? 'user') === 'admin'; }
}
