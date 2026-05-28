<?php

class ExportController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        // Accept token from query string for direct browser downloads
        if (!empty($_GET['token']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
        }
        parent::__construct();
        $this->pdo = DB::getConnection();
    }

    // campings

    public function campingsCsv(): void
    {
        $this->requireAdmin();
        $this->sendCsv('campings', $this->fetchCampings());
    }

    public function campingsJson(): void
    {
        $this->requireAdmin();
        $this->sendJson('campings', $this->fetchCampings());
    }

    private function fetchCampings(): array
    {
        return $this->pdo->query("
            SELECT c.id, c.name, c.slug, c.type, c.address, c.region,
                   c.latitude, c.longitude, c.price_per_night, c.capacity,
                   c.rating_avg, c.rating_count, c.is_published, c.approval_status,
                   c.created_at, u.username AS created_by
            FROM campings c
            LEFT JOIN users u ON u.id = c.created_by
            ORDER BY c.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // bookings

    public function bookingsCsv(): void
    {
        $this->requireAdmin();
        $this->sendCsv('bookings', $this->fetchBookings());
    }

    public function bookingsJson(): void
    {
        $this->requireAdmin();
        $this->sendJson('bookings', $this->fetchBookings());
    }

    private function fetchBookings(): array
    {
        return $this->pdo->query("
            SELECT b.id, b.check_in, b.check_out, b.guests, b.total_price, b.status,
                   b.created_at, u.username AS user, c.name AS camping
            FROM bookings b
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN campings c ON c.id = b.camping_id
            ORDER BY b.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // reviews

    public function reviewsCsv(): void
    {
        $this->requireAdmin();
        $this->sendCsv('reviews', $this->fetchReviews());
    }

    public function reviewsJson(): void
    {
        $this->requireAdmin();
        $this->sendJson('reviews', $this->fetchReviews());
    }

    private function fetchReviews(): array
    {
        return $this->pdo->query("
            SELECT r.id, r.rating, r.title, r.content, r.created_at,
                   u.username AS user, c.name AS camping
            FROM reviews r
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN campings c ON c.id = r.camping_id
            ORDER BY r.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // users

    public function usersCsv(): void
    {
        $this->requireAdmin();
        $this->sendCsv('users', $this->fetchUsers());
    }

    public function usersJson(): void
    {
        $this->requireAdmin();
        $this->sendJson('users', $this->fetchUsers());
    }

    private function fetchUsers(): array
    {
        return $this->pdo->query("
            SELECT id, username, email, full_name, role, oauth_provider, created_at
            FROM users
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // output helpers

    private function sendCsv(string $name, array $rows): void
    {
        if (empty($rows)) {
            $this->json(['error' => 'No data'], 404);
        }

        $filename = $name . '_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    private function sendJson(string $name, array $rows): void
    {
        $filename = $name . '_' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
