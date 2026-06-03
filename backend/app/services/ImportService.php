<?php
class ImportService
{
    public function __construct(private readonly CampingsRepository $campings) {}

    public function importCampings(array $rows, int $createdBy): array
    {
        $inserted = 0;
        $errors   = [];
        foreach ($rows as $i => $row) {
            try {
                $this->insertCamping($row, $createdBy);
                $inserted++;
            } catch (Exception $e) {
                $errors[] = ['row' => $i + 1, 'error' => $e->getMessage()];
            }
        }
        return ['inserted' => $inserted, 'total' => count($rows), 'errors' => $errors];
    }

    private function insertCamping(array $row, int $createdBy): void
    {
        foreach (['name', 'latitude', 'longitude'] as $req) {
            if (!isset($row[$req]) || trim((string)$row[$req]) === '')
                throw new ValidationException("Camp obligatoriu lipsa: $req");
        }
        $name = trim($row['name']);
        if (strlen($name) < 2) throw new ValidationException('Numele e prea scurt');

        $lat = (float)$row['latitude'];
        $lng = (float)$row['longitude'];
        if ($lat === 0.0 && $lng === 0.0) throw new ValidationException('Coordonate invalide (0,0)');
        if ($lat < -90 || $lat > 90)       throw new ValidationException("Latitudine invalida: $lat");
        if ($lng < -180 || $lng > 180)     throw new ValidationException("Longitudine invalida: $lng");

        $validTypes = ['tent', 'cabin', 'glamping', 'rv', 'wild'];
        $slug = $this->campings->makeUniqueSlug($name);

        $this->campings->createFromImport($createdBy, $slug, [
            'name'            => $name,
            'description'     => $row['description'] ?? null,
            'type'            => in_array($row['type'] ?? '', $validTypes, true) ? $row['type'] : 'tent',
            'address'         => $row['address']  ?? null,
            'region'          => $row['region']   ?? null,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'price_per_night' => isset($row['price_per_night']) && $row['price_per_night'] !== '' ? (float)$row['price_per_night'] : null,
            'capacity'        => isset($row['capacity']) && $row['capacity'] !== '' ? (int)$row['capacity'] : null,
        ]);
    }
}
