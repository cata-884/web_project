<?php
class ImportController extends Controller
{
    private ImportService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ImportService(new CampingsRepository());
    }

    public function campings(): void
    {
        $admin = $this->requireAdmin();
        $file  = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Fisier lipsa sau eroare upload (cod ' . ($file['error'] ?? '-') . ')'], 400);
        }

        $rows = $this->parseFile($file);
        if ($rows === null) {
            $this->json(['error' => 'Format nesuportat sau fisier corupt. Acceptat: CSV, JSON'], 400);
        }

        $this->json($this->service->importCampings($rows, (int)$admin['id']));
    }

    private function parseFile(array $file): ?array
    {
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = strtolower($file['type'] ?? '');

        if ($ext === 'json' || str_contains($mime, 'json')) return $this->parseJson($file['tmp_name']);
        if ($ext === 'csv'  || str_contains($mime, 'csv') || str_contains($mime, 'text/plain')) return $this->parseCsv($file['tmp_name']);
        return null;
    }

    private function parseCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return null;
        //consumam cele 3 caractere, in caz contrar o luam de la inceput
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        //cream lista de atribute
        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); return null; }
        $headers = array_map('trim', $headers);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === count($headers)) $rows[] = array_combine($headers, $line);
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
        return array_is_list($data) ? $data : ($data['data'] ?? null);
    }
}
