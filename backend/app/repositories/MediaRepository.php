<?php
class MediaRepository extends Repository
{
    public function findByCampingId(int $campingId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM camping_media WHERE camping_id = :camping_id ORDER BY created_at"
        );
        $stmt->execute(['camping_id' => $campingId]);
        return $stmt->fetchAll();
    }

    public function findByReviewId(int $reviewId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, review_id, type, created_at FROM review_media WHERE review_id = :review_id ORDER BY created_at"
        );
        $stmt->execute(['review_id' => $reviewId]);
        return $stmt->fetchAll();
    }

    public function createForCamping(int $campingId, string $type, string $url): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO camping_media (camping_id, type, url) VALUES (:camping_id, :type, :url) RETURNING id"
        );
        $stmt->execute(['camping_id' => $campingId, 'type' => $type, 'url' => $url]);
        return (int) $stmt->fetchColumn();
    }

    public function createForReview(int $reviewId, string $type, string $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO review_media (review_id, type, data) VALUES (:review_id, :type, decode(:data, 'hex')) RETURNING id"
        );
        $stmt->execute(['review_id' => $reviewId, 'type' => $type, 'data' => bin2hex($data)]);
        return (int) $stmt->fetchColumn();
    }

    public function getReviewMediaData(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, type, encode(data, 'hex') AS data_hex FROM review_media WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['data'] = hex2bin($row['data_hex']);
        unset($row['data_hex']);
        return $row;
    }

    public function deleteCampingMedia(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM camping_media WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function deleteReviewMedia(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM review_media WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getCampingMediaOwner(int $mediaId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.created_by FROM camping_media m JOIN campings c ON c.id = m.camping_id WHERE m.id = :id"
        );
        $stmt->execute(['id' => $mediaId]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    public function getReviewMediaOwner(int $mediaId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.user_id FROM review_media m JOIN reviews r ON r.id = m.review_id WHERE m.id = :id"
        );
        $stmt->execute(['id' => $mediaId]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }
}
