<?php

class SectionsModel extends Model
{
    protected string $table = 'user_sections';

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM section_campings sc
                     WHERE sc.section_id = s.id) AS campings_count
             FROM user_sections s
             WHERE s.user_id = :user_id
             ORDER BY s.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM section_campings sc
                     WHERE sc.section_id = s.id) AS campings_count
             FROM user_sections s
             WHERE s.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, string $name, string $color = '#4A90D9'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_sections (user_id, name, color)
             VALUES (:user_id, :name, :color)
             RETURNING id"
        );
        $stmt->execute([
            'user_id' => $userId,
            'name'    => $name,
            'color'   => $color,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'color'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;

        $sql = "UPDATE user_sections SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_sections WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getCampings(int $sectionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.slug, c.type, c.region,
                    c.latitude, c.longitude, c.price_per_night,
                    c.rating_avg, c.rating_count,
                    sc.added_at
             FROM section_campings sc
             JOIN campings c ON c.id = sc.camping_id
             WHERE sc.section_id = :section_id
             ORDER BY sc.added_at DESC"
        );
        $stmt->execute(['section_id' => $sectionId]);
        return $stmt->fetchAll();
    }

    public function addCamping(int $sectionId, int $campingId): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO section_campings (section_id, camping_id)
             VALUES (:section_id, :camping_id)
             ON CONFLICT (section_id, camping_id) DO NOTHING"
        );
        return $stmt->execute([
            'section_id' => $sectionId,
            'camping_id' => $campingId,
        ]);
    }

    public function removeCamping(int $sectionId, int $campingId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM section_campings
             WHERE section_id = :section_id AND camping_id = :camping_id"
        );
        return $stmt->execute([
            'section_id' => $sectionId,
            'camping_id' => $campingId,
        ]);
    }

    public function isOwner(int $sectionId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM user_sections
             WHERE id = :id AND user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $sectionId, 'user_id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }
}
