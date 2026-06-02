<?php
class CampingsService
{
    private const ZONE_ENV_MAP = [
        'mountain'    => ['munte', 'pășune alpină'],
        'seaside'     => ['plajă'],
        'delta'       => ['deltă'],
        'forest_lake' => ['pădure', 'lângă lac', 'lângă râu'],
    ];

    public function __construct(private CampingsRepository $campings) {}

    public function listMine(int $userId): array
    {
        return $this->campings->findByUser($userId);
    }

    public function search(array $raw): array
    {
        $validTypes = ['wild','glamping','rv','tent','cabin'];
        $types = array_filter((array)($raw['type'] ?? []), fn($t) => in_array($t, $validTypes, true));

        $envs = [];
        foreach ((array)($raw['zone'] ?? []) as $zone) {
            foreach (self::ZONE_ENV_MAP[$zone] ?? [] as $tag) $envs[] = $tag;
        }

        $filters = [
            'region'    => $raw['region']    ?? null,
            'types'     => array_values($types) ?: null,
            'envs'      => $envs ?: null,
            'min_price' => $raw['min_price'] ?? null,
            'max_price' => $raw['max_price'] ?? null,
            'min_rating'=> $raw['min_rating'] ?? null,
            'search'    => $raw['search']    ?? null,
            'limit'     => (int)($raw['limit']  ?? 20),
            'offset'    => (int)($raw['offset'] ?? 0),
        ];

        return [
            'campings' => $this->campings->search($filters),
            'total'    => $this->campings->countSearch($filters),
            'limit'    => $filters['limit'],
            'offset'   => $filters['offset'],
        ];
    }

    public function getById(int $id): array
    {
        $c = $this->campings->findById($id);
        if (!$c) throw new NotFoundException('Camping inexistent');
        return $c;
    }

    public function getBySlug(string $slug): array
    {
        $c = $this->campings->findBySlug($slug);
        if (!$c) throw new NotFoundException('Camping inexistent');
        return $c;
    }

    public function mapMarkers(string $bbox): array
    {
        $parts = array_map('floatval', explode(',', $bbox));
        if (count($parts) !== 4) throw new ValidationException('bbox invalid');
        [$s, $w, $n, $e] = $parts;
        return $this->campings->findInBbox($s, $w, $n, $e);
    }

    public function create(int $userId, array $data): array
    {
        $this->validateCreate($data);
        $id = $this->campings->create($userId, $data);
        return $this->campings->findById($id);
    }

    public function update(int $id, array $data, array $user): array
    {
        $this->assertCanModify($id, $user);
        if (!$data) throw new ValidationException('Body gol');
        $this->campings->update($id, $data);
        return $this->campings->findById($id);
    }

    public function delete(int $id, array $user): void
    {
        $this->assertCanModify($id, $user);
        $this->campings->delete($id);
    }

    public function resubmit(int $id, array $user): void
    {
        $camping = $this->campings->findById($id);
        if (!$camping) throw new NotFoundException('Camping inexistent');
        if ((int)$camping['created_by'] !== (int)$user['id'])
            throw new ForbiddenException('Nu ai permisiunea sa modifici aceasta cerere');
        $status = (int)$camping['approval_status'];
        if ($status !== -1 && $status !== 2)
            throw new ValidationException('Cererea nu poate fi retrimisa (status curent: ' . $status . ')');
        $this->campings->resubmit($id);
    }

    private function validateCreate(array $data): void
    {
        if (empty($data['name']) || strlen($data['name']) < 3 || strlen($data['name']) > 200)
            throw new ValidationException('name obligatoriu (3-200 caractere)');
        if (!isset($data['latitude'], $data['longitude']))
            throw new ValidationException('latitude si longitude obligatorii');
        if ($data['latitude'] < -90 || $data['latitude'] > 90)
            throw new ValidationException('latitude invalid');
        if ($data['longitude'] < -180 || $data['longitude'] > 180)
            throw new ValidationException('longitude invalid');
        $validTypes = ['wild','glamping','rv','tent','cabin'];
        if (!empty($data['type']) && !in_array($data['type'], $validTypes, true))
            throw new ValidationException('type invalid; valori: ' . implode(', ', $validTypes));
    }

    private function assertCanModify(int $campingId, array $user): void
    {
        $ownerId = $this->campings->getOwnerId($campingId);
        if ($ownerId === null) throw new NotFoundException('Camping inexistent');
        if (($user['role'] ?? 'user') === 'admin') return;
        if ((int)$user['id'] === $ownerId) return;
        throw new ForbiddenException('Nu ai drepturi pe acest camping');
    }
}
