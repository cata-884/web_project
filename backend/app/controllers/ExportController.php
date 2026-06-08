<?php

use JetBrains\PhpStorm\NoReturn;

class ExportController extends Controller
{
    private ExportService $service;

    public function __construct()
    {
        if (!empty($_GET['token']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
        }
        parent::__construct();
        $this->service = new ExportService(
            new CampingsRepository(),
            new BookingsRepository(),
            new ReviewsRepository(),
            new UserRepository(),
        );
    }

    #[NoReturn]
    public function campingsCsv(): void  { $this->requireAdmin(); $this->sendCsv('campings', $this->service->getCampings()); }
    #[NoReturn]
    public function campingsJson(): void { $this->requireAdmin(); $this->sendJson('campings', $this->service->getCampings()); }
    #[NoReturn]
    public function bookingsCsv(): void  { $this->requireAdmin(); $this->sendCsv('bookings', $this->service->getBookings()); }
    #[NoReturn]
    public function bookingsJson(): void { $this->requireAdmin(); $this->sendJson('bookings', $this->service->getBookings()); }
    #[NoReturn]
    public function reviewsCsv(): void   { $this->requireAdmin(); $this->sendCsv('reviews', $this->service->getReviews()); }
    #[NoReturn]
    public function reviewsJson(): void  { $this->requireAdmin(); $this->sendJson('reviews', $this->service->getReviews()); }
    #[NoReturn]
    public function usersCsv(): void     { $this->requireAdmin(); $this->sendCsv('users', $this->service->getUsers()); }
    #[NoReturn]
    public function usersJson(): void    { $this->requireAdmin(); $this->sendJson('users', $this->service->getUsers()); }

    #[NoReturn]
    private function sendCsv(string $name, array $rows): void
    {
        if (empty($rows)) $this->json(['error' => 'No data'], 404);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store');
        $out = fopen('php://output', 'w');
        //flag pentru diacritice
        fwrite($out, "\xEF\xBB\xBF");
        //extrage id, nume, slug, etc etc
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($out, array_values($row));
        fclose($out);
        exit;
    }

    #[NoReturn]
    private function sendJson(string $name, array $rows): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '_' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-cache, no-store');
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
