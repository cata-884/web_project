<?php
use JetBrains\PhpStorm\NoReturn;

abstract class Controller
{
    protected ?array  $currentUser  = null;
    protected ?string $currentToken = null;
    protected ?string $bannedReason = null;

    public function __construct()
    {
        $this->resolveCurrentUser();
    }

    private function resolveCurrentUser(): void
    {
        $token = $this->extractBearerToken();
        if (!$token) return;

        $userId = (new SessionRepository())->validateToken($token);
        if (!$userId) return;

        $user = (new UserRepository())->findById($userId);
        if (!$user) return;

        $this->currentUser  = $user;
        $this->currentToken = $token;

        if (($user['role'] ?? 'user') !== 'admin') {
            $ban = (new BansRepository())->findActiveByUserId((int)$user['id']);
            if ($ban) $this->bannedReason = $ban['reason'];
        }
    }

    private function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        return str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : null;
    }

    #[NoReturn]
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }

    protected function getJsonBody(): array
    {
        $raw     = file_get_contents('php://input');
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    protected function requireAuth(): array
    {
        if ($this->currentUser === null) throw new UnauthorizedException();
        if ($this->bannedReason !== null) throw new ForbiddenException('Contul tau este suspendat: ' . $this->bannedReason);
        return $this->currentUser;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (($user['role'] ?? 'user') !== 'admin') throw new ForbiddenException('Acces interzis');
        return $user;
    }

    protected function requireOrganizer(): array
    {
        $user = $this->requireAuth();
        $role = $user['role'] ?? 'user';
        if ($role !== 'organizer' && $role !== 'admin') throw new ForbiddenException('Necesita rol de organizer');
        return $user;
    }
}
