<?php

require_once __DIR__ . '/../core/Controller.php';

class ImportController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        parent::__construct();
        $this->pdo = DB::getConnection();
    }

    public function campings(): void
    {
        $admin = $this->requireAdmin();

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Fisier lipsa sau eroare upload (cod ' . ($file['error'] ?? '-') . ')'], 400);
        }

        $rows = $this->parseFile($file);
        if ($rows === null) {
            $this->json(['error' => 'Format nesuportat sau fisier corupt. Acceptat: CSV, JSON'], 400);
        }

        $inserted = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            try {
                $this->insertCamping($row, (int) $admin['id']);
                $inserted++;
            } catch (Exception $e) {
                $errors[] = ['row' => $i + 1, 'error' => $e->getMessage()];
            }
        }

        $this->json([
            'inserted' => $inserted,
            'total'    => count($rows),
            'errors'   => $errors,
        ]);
    }

    // ===== PARSE =====

    private function parseFile(array $file): ?array
    {
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = strtolower($file['type'] ?? '');

        if ($ext === 'json' || str_contains($mime, 'json')) {
            return $this->parseJson($file['tmp_name']);
        }

        if ($ext === 'csv' || str_contains($mime, 'csv') || str_contains($mime, 'text/plain')) {
            return $this->parseCsv($file['tmp_name']);
        }

        return null;
    }

    private function parseCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return null;

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); return null; }
        $headers = array_map('trim', $headers);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }
        fclose($handle);
        return $rows;
    }

    private function parseJson(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) return null;
        $data = json_decode($content, true);
        if (!is_array($data)) return null;
        // Accept bare array or {"data": [...]}
        return array_is_list($data) ? $data : ($data['data'] ?? null);
    }

    // ===== INSERT =====

    private function insertCamping(array $row, int $createdBy): void
    {
        foreach (['name', 'latitude', 'longitude'] as $req) {
            if (!isset($row[$req]) || trim((string)$row[$req]) === '') {
                throw new Exception("Camp obligatoriu lipsa: $req");
            }
        }

        $name = trim($row['name']);
        if (strlen($name) < 2) throw new Exception("Numele e prea scurt");

        $lat = (float) $row['latitude'];
        $lng = (float) $row['longitude'];
        if ($lat === 0.0 && $lng === 0.0) throw new Exception("Coordonate invalide (0,0)");
        if ($lat < -90 || $lat > 90)      throw new Exception("Latitudine invalida: $lat");
        if ($lng < -180 || $lng > 180)    throw new Exception("Longitudine invalida: $lng");

        $validTypes = ['tent', 'cabin', 'glamping', 'rv', 'wild'];
        $type = in_array($row['type'] ?? '', $validTypes, true) ? $row['type'] : 'tent';

        $slug = $this->uniqueSlug($name);

        $stmt = $this->pdo->prepare("
            INSERT INTO campings
                (created_by, name, slug, description, type, address, region,
                 latitude, longitude, price_per_night, capacity,
                 is_published, approval_status)
            VALUES
                (:created_by, :name, :slug, :description, :type, :address, :region,
                 :lat, :lng, :price, :capacity,
                 TRUE, 1)
        ");

        $stmt->execute([
            'created_by'  => $createdBy,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $row['description'] ?? null,
            'type'        => $type,
            'address'     => $row['address']     ?? null,
            'region'      => $row['region']      ?? null,
            'lat'         => $lat,
            'lng'         => $lng,
            'price'       => isset($row['price_per_night']) && $row['price_per_night'] !== ''
                                 ? (float) $row['price_per_night'] : null,
            'capacity'    => isset($row['capacity']) && $row['capacity'] !== ''
                                 ? (int) $row['capacity'] : null,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = preg_replace('/-+/', '-', trim(preg_replace(
            '/[^a-z0-9]+/', '-', strtolower($name)
        ), '-'));

        $slug = $base;
        $n    = 0;
        while (true) {
            $st = $this->pdo->prepare("SELECT id FROM campings WHERE slug = :s");
            $st->execute(['s' => $slug]);
            if (!$st->fetch()) break;
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }
}
