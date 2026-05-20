<?php

/**
 * MediaModel — gestiune fisiere multimedia (imagine, audio, video)
 * pentru campinguri si recenzii.
 */
class MediaModel extends Model
{
    protected string $table = 'camping_media';

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM camping_media WHERE id = :id
             UNION ALL
             SELECT * FROM review_media WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByCampingId(int $campingId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM camping_media
             WHERE camping_id = :camping_id
             ORDER BY sort_order"
        );
        $stmt->execute(['camping_id' => $campingId]);
        return $stmt->fetchAll();
    }

    public function findByReviewId(int $reviewId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM review_media
             WHERE review_id = :review_id
             ORDER BY created_at"
        );
        $stmt->execute(['review_id' => $reviewId]);
        return $stmt->fetchAll();
    }

    public function createForCamping(int $campingId, string $type, string $url, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO camping_media (camping_id, type, url, sort_order)
             VALUES (:camping_id, :type, :url, :sort_order)
             RETURNING id"
        );
        $stmt->execute([
            'camping_id' => $campingId,
            'type'       => $type,
            'url'        => $url,
            'sort_order' => $sortOrder,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function createForReview(int $reviewId, string $type, string $url): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO review_media (review_id, type, url)
             VALUES (:review_id, :type, :url)
             RETURNING id"
        );
        $stmt->execute([
            'review_id' => $reviewId,
            'type'      => $type,
            'url'       => $url,
        ]);
        return (int) $stmt->fetchColumn();
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
            "SELECT c.created_by FROM camping_media m
             JOIN campings c ON c.id = m.camping_id
             WHERE m.id = :id"
        );
        $stmt->execute(['id' => $mediaId]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    public function getReviewMediaOwner(int $mediaId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.user_id FROM review_media m
             JOIN reviews r ON r.id = m.review_id
             WHERE m.id = :id"
        );
        $stmt->execute(['id' => $mediaId]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    /**
     * Returneaza urmatorul sort_order pentru un camping.
     */
    public function nextSortOrder(int $campingId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM camping_media WHERE camping_id = :id"
        );
        $stmt->execute(['id' => $campingId]);
        return (int) $stmt->fetchColumn();
    }
}
