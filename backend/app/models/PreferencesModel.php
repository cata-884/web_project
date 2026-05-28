<?php
class PreferencesModel extends Model
{
    protected string $table = 'user_preferences';

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

        $stmt = $this->pdo->prepare("
            INSERT INTO user_preferences (user_id, camping_types, travel_styles, preferred_zones, updated_at)
            VALUES (:uid, :ct, :ts, :pz, NOW())
            ON CONFLICT (user_id) DO UPDATE SET
                camping_types  = EXCLUDED.camping_types,
                travel_styles  = EXCLUDED.travel_styles,
                preferred_zones= EXCLUDED.preferred_zones,
                updated_at     = NOW()
        ");
        $stmt->execute([
            'uid' => $userId,
            'ct'  => $toArr($types),
            'ts'  => $toArr($styles),
            'pz'  => $toArr($zones),
        ]);
    }

    private function parsePgArr(string $raw): array
    {
        $raw = trim($raw, '{}');
        if ($raw === '') return [];
        return explode(',', $raw);
    }

    public function decode(array $row): array
    {
        return [
            'camping_types'  => $this->parsePgArr($row['camping_types']  ?? ''),
            'travel_styles'  => $this->parsePgArr($row['travel_styles']  ?? ''),
            'preferred_zones'=> $this->parsePgArr($row['preferred_zones'] ?? ''),
        ];
    }
}
