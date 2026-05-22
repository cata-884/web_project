<?php

/**
 * BookingsModel
 *
 * Status-uri posibile: pending → confirmed → completed
 *                      pending → cancelled
 * Pretul total se calculeaza automat pe baza price_per_night × nopti.
 */
class BookingsModel extends Model
{
    protected string $table = 'bookings';

    /**
     * Rezervarile unui user (cu detalii camping), ordonate descrescator.
     */
    public function findByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit  = min(100, max(1, $limit));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                    c.name AS camping_name, c.slug AS camping_slug,
                    c.type AS camping_type, c.region AS camping_region,
                    c.latitude, c.longitude
             FROM bookings b
             JOIN campings c ON c.id = b.camping_id
             WHERE b.user_id = :user_id
             ORDER BY b.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Rezervarile pentru un camping (vizibil pentru owner/admin).
     */
    public function findByCampingId(int $campingId, int $limit = 50, int $offset = 0): array
    {
        $limit  = min(100, max(1, $limit));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                    u.username, u.email, u.full_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.camping_id = :camping_id
             ORDER BY b.check_in DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(['camping_id' => $campingId]);
        return $stmt->fetchAll();
    }

    /**
     * Gaseste o rezervare dupa ID (cu detalii camping).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                    c.name AS camping_name, c.slug AS camping_slug,
                    c.type AS camping_type, c.region AS camping_region,
                    c.price_per_night AS camping_price,
                    u.username
             FROM bookings b
             JOIN campings c ON c.id = b.camping_id
             JOIN users u ON u.id = b.user_id
             WHERE b.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Creeaza o rezervare. Calculeaza total_price automat.
     */
    public function create(int $userId, int $campingId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, total_price, status)
             VALUES (:user_id, :camping_id, :check_in, :check_out, :guests, :total_price, 'pending')
             RETURNING id"
        );
        $stmt->execute([
            'user_id'     => $userId,
            'camping_id'  => $campingId,
            'check_in'    => $data['check_in'],
            'check_out'   => $data['check_out'],
            'guests'      => $data['guests'],
            'total_price' => $data['total_price'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Actualizeaza status-ul unei rezervari.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE bookings SET status = :status WHERE id = :id"
        );
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Actualizeaza campuri permise (date, guests).
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['check_in', 'check_out', 'guests', 'total_price', 'status'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;

        $sql = "UPDATE bookings SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Returneaza user_id-ul rezervarii.
     */
    public function getOwnerId(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM bookings WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    /**
     * Verifica daca un camping e disponibil intr-un interval de date.
     * Returneaza true daca NU sunt suprapuneri cu rezervari confirmate/pending.
     */
    public function checkAvailability(int $campingId, string $checkIn, string $checkOut): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE camping_id = :camping_id
               AND status IN ('pending', 'confirmed')
               AND check_in < :check_out
               AND check_out > :check_in"
        );
        $stmt->execute([
            'camping_id' => $campingId,
            'check_in'   => $checkIn,
            'check_out'  => $checkOut,
        ]);
        return (int)$stmt->fetchColumn() === 0;
    }

    /**
     * Calculeaza pretul total: price_per_night × nr_nopti × guests.
     */
    public function calculatePrice(float $pricePerNight, string $checkIn, string $checkOut, int $guests): float
    {
        $nights = (int)(new DateTime($checkIn))->diff(new DateTime($checkOut))->days;
        if ($nights < 1) $nights = 1;
        return round($pricePerNight * $nights, 2);
    }

    /**
     * Numarul total de rezervari ale unui user (pentru paginare).
     */
    public function countByUserId(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
