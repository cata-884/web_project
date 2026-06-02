<?php
class ReviewsRepository extends Repository
{
    public function findByCampingId(int $campingId, int $limit = 20, int $offset = 0): array
    {
        $limit  = min(100, max(1, $limit));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.user_id, r.camping_id, r.rating, r.content,
                    r.created_at, u.username, u.avatar_url,
                    COALESCE(
                      (SELECT json_agg(json_build_object('id', rm.id, 'type', rm.type))
                       FROM review_media rm WHERE rm.review_id = r.id),
                      '[]'::json
                    ) AS media
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.camping_id = :camping_id
             ORDER BY r.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(['camping_id' => $campingId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) { $row['media'] = json_decode($row['media'], true); }
        return $rows;
    }

    public function countByCampingId(int $campingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews WHERE camping_id = :camping_id");
        $stmt->execute(['camping_id' => $campingId]);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username, u.avatar_url FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findForExport(): array
    {
        return $this->pdo->query("
            SELECT r.id, r.rating, r.content, r.created_at,
                   u.username AS user, c.name AS camping
            FROM reviews r
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN campings c ON c.id = r.camping_id
            ORDER BY r.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, int $campingId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reviews (user_id, camping_id, booking_id, rating, content)
             VALUES (:user_id, :camping_id, :booking_id, :rating, :content)
             RETURNING id"
        );
        $stmt->execute([
            'user_id'    => $userId,
            'camping_id' => $campingId,
            'booking_id' => $data['booking_id'] ?? null,
            'rating'     => $data['rating'],
            'content'    => $data['content'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['rating', 'content'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;
        $stmt = $this->pdo->prepare("UPDATE reviews SET " . implode(', ', $sets) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM reviews WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getOwnerId(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM reviews WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    public function userAlreadyReviewed(int $userId, int $campingId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM reviews WHERE user_id = :user_id AND camping_id = :camping_id LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'camping_id' => $campingId]);
        return (bool) $stmt->fetchColumn();
    }
}
