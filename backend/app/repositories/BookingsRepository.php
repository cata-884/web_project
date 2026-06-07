<?php
class BookingsRepository extends Repository
{
    public static function totalPriceExpr(string $b = 'b', string $c = 'c'): string
    {
        return "$c.price_per_night * ($b.check_out - $b.check_in) * $b.guests";
    }

    public function findByUserId(int $userId, int $limit = 50, int $offset = 0, ?int $campingId = null): array
    {
        $limit  = min(100, max(1, $limit));
        $offset = max(0, $offset);
        $tp     = self::totalPriceExpr();

        $where  = 'b.user_id = :user_id';
        $params = ['user_id' => $userId];
        if ($campingId !== null) {
            $where .= ' AND b.camping_id = :camping_id';
            $params['camping_id'] = $campingId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                    c.name AS camping_name, c.slug AS camping_slug,
                    c.type AS camping_type, c.region AS camping_region,
                    c.latitude, c.longitude,
                    $tp AS total_price,
                    (SELECT url FROM camping_media WHERE camping_id = c.id ORDER BY created_at LIMIT 1) AS cover_url
             FROM bookings b
             JOIN campings c ON c.id = b.camping_id
             WHERE $where
             ORDER BY b.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $tp = self::totalPriceExpr();
        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                    c.name AS camping_name, c.slug AS camping_slug,
                    c.type AS camping_type, c.region AS camping_region,
                    c.price_per_night AS camping_price,
                    u.username,
                    $tp AS total_price
             FROM bookings b
             JOIN campings c ON c.id = b.camping_id
             JOIN users u ON u.id = b.user_id
             WHERE b.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findForExport(): array
    {
        $tp = self::totalPriceExpr();
        return $this->pdo->query("
            SELECT b.id, b.check_in, b.check_out, b.guests,
                   $tp AS total_price,
                   b.status, b.created_at, u.username AS \"user\", c.name AS camping
            FROM bookings b
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN campings c ON c.id = b.camping_id
            ORDER BY b.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, int $campingId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, status)
             VALUES (:user_id, :camping_id, :check_in, :check_out, :guests, 'pending')
             RETURNING id"
        );
        $stmt->execute([
            'user_id'    => $userId,
            'camping_id' => $campingId,
            'check_in'   => $data['check_in'],
            'check_out'  => $data['check_out'],
            'guests'     => $data['guests'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function completeExpired(int $userId): void
    {
        $this->pdo->prepare(
            "UPDATE bookings SET status = 'completed'
             WHERE user_id = :user_id AND status IN ('pending', 'confirmed') AND check_out < CURRENT_DATE"
        )->execute(['user_id' => $userId]);
    }

    public function cancelById(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE bookings SET status = 'cancelled'
             WHERE id = :id AND user_id = :user_id AND status IN ('pending', 'confirmed')"
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function checkAvailability(int $campingId, string $checkIn, string $checkOut): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE camping_id = :camping_id
               AND status IN ('pending', 'confirmed')
               AND check_in < :check_out
               AND check_out > :check_in"
        );
        $stmt->execute(['camping_id' => $campingId, 'check_in' => $checkIn, 'check_out' => $checkOut]);
        return (int) $stmt->fetchColumn() === 0;
    }

    public function countByUserId(int $userId, ?int $campingId = null): int
    {
        $where  = 'user_id = :user_id';
        $params = ['user_id' => $userId];
        if ($campingId !== null) {
            $where .= ' AND camping_id = :camping_id';
            $params['camping_id'] = $campingId;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
