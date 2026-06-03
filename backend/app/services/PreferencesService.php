<?php
class PreferencesService
{
    private const VALID_TYPES  = ['wild', 'glamping', 'rv', 'tent', 'cabin'];
    private const VALID_STYLES = ['solo', 'couple', 'family', 'group', 'pets'];
    private const VALID_ZONES  = ['mountain', 'seaside', 'delta', 'forest_lake'];

    public function __construct(private readonly PreferencesRepository $prefs) {}

    public function get(int $userId): array
    {
        $row = $this->prefs->getByUser($userId);
        if (!$row) return ['camping_types' => [], 'travel_styles' => [], 'preferred_zones' => []];
        return $this->prefs->decode($row);
    }

    public function save(int $userId, array $data): void
    {
        $filter = fn(array $val, array $allowed) =>
            array_values(array_filter($val, fn($v) => in_array($v, $allowed, true)));

        $this->prefs->upsert(
            $userId,
            $filter($data['camping_types']   ?? [], self::VALID_TYPES),
            $filter($data['travel_styles']   ?? [], self::VALID_STYLES),
            $filter($data['preferred_zones'] ?? [], self::VALID_ZONES),
        );
    }
}
