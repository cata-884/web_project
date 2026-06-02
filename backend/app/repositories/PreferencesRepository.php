<?php
class PreferencesRepository extends Repository
{
    public function getByUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT camping_types, travel_styles, preferred_zones FROM user_preferences WHERE user_id = :uid"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function upsert(int $userId, array $types, array $styles, array $zones): void
    {
        $toArr = fn(array $a) => empty($a) ? '{}' : '{' . implode(',', $a) . '}';
        $this->pdo->prepare("
            INSERT INTO user_preferences (user_id, camping_types, travel_styles, preferred_zones, updated_at)
            VALUES (:uid, :ct, :ts, :pz, NOW())
            ON CONFLICT (user_id) DO UPDATE SET
                camping_types   = EXCLUDED.camping_types,
                travel_styles   = EXCLUDED.travel_styles,
                preferred_zones = EXCLUDED.preferred_zones,
                updated_at      = NOW()
        ")->execute(['uid' => $userId, 'ct' => $toArr($types), 'ts' => $toArr($styles), 'pz' => $toArr($zones)]);
    }

    public function decode(array $row): array
    {
        $parse = fn(string $raw): array => ($s = trim($raw, '{}')) === '' ? [] : explode(',', $s);
        return [
            'camping_types'   => $parse($row['camping_types']   ?? ''),
            'travel_styles'   => $parse($row['travel_styles']   ?? ''),
            'preferred_zones' => $parse($row['preferred_zones'] ?? ''),
        ];
    }
}
