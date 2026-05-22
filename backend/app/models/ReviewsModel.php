<?php

/**
 * ReviewsModel — CRUD pentru recenzii la locuri de camping.
 *
 * Fiecare user poate lasa maxim o recenzie per camping (UNIQUE constraint).
 * Rating-ul mediu al campingului se recalculează automat via trigger PL/pgSQL.
 */
class ReviewsModel extends Model
{
    protected string $table = 'reviews';

    /**
     * Listeaza recenziile unui camping, cu paginare.
     * Include username-ul autorului si media atasata.
     */
    public function findByCampingId(int $campingId, int $limit = 20, int $offset = 0): array
    {
        $limit  = min(100, max(1, $limit));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.user_id, r.camping_id, r.rating, r.title, r.content,
                    r.created_at,
                    u.username, u.avatar_url,
                    COALESCE(
                      (SELECT json_agg(json_build_object(
                                  'id', rm.id, 'type', rm.type, 'url', rm.url
                              ))
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

        foreach ($rows as &$row) {
            $row['media'] = json_decode($row['media'], true);
        }
        return $rows;
    }

    /**
     * Numarul total de recenzii pentru un camping (pentru paginare).
     */
    public function countByCampingId(int $campingId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM reviews WHERE camping_id = :camping_id"
        );
        $stmt->execute(['camping_id' => $campingId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Gaseste o recenzie dupa ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username, u.avatar_url
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Creeaza o recenzie. Returneaza ID-ul creat.
     * Arunca exceptie daca userul a recenzat deja acest camping (UNIQUE constraint).
     */
    public function create(int $userId, int $campingId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reviews (user_id, camping_id, booking_id, rating, title, content)
             VALUES (:user_id, :camping_id, :booking_id, :rating, :title, :content)
             RETURNING id"
        );
        $stmt->execute([
            'user_id'    => $userId,
            'camping_id' => $campingId,
            'booking_id' => $data['booking_id'] ?? null,
            'rating'     => $data['rating'],
            'title'      => $data['title'] ?? null,
            'content'    => $data['content'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Actualizeaza rating, title, content.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['rating', 'title', 'content'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;

        $sql = "UPDATE reviews SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Sterge o recenzie.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM reviews WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Returneaza user_id-ul autorului recenziei.
     */
    public function getOwnerId(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM reviews WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    /**
     * Verifica daca un user a recenzat deja un camping.
     */
    public function userAlreadyReviewed(int $userId, int $campingId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM reviews
             WHERE user_id = :user_id AND camping_id = :camping_id
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'camping_id' => $campingId]);
        return (bool) $stmt->fetchColumn();
    }
}
