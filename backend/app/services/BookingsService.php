<?php
readonly class BookingsService
{
    public function __construct(
        private BookingsRepository $bookings,
        private CampingsRepository $campings,
    ) {}

    public function listForUser(int $userId, int $limit, int $offset, ?int $campingId): array
    {
        $this->bookings->completeExpired($userId);
        return [
            'items' => $this->bookings->findByUserId($userId, $limit, $offset, $campingId),
            'total' => $this->bookings->countByUserId($userId, $campingId),
        ];
    }

    public function cancel(int $id, int $userId): array
    {
        if (!$this->bookings->cancelById($id, $userId))
            throw new ValidationException('Rezervarea nu poate fi anulată (deja anulată, finalizată sau nu îți aparține)');
        return $this->bookings->findById($id);
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

}
