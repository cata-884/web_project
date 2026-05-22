<?php

use JetBrains\PhpStorm\NoReturn;

abstract class Controller
{
    protected mixed $model        = null;
    protected ?array $currentUser = null;
    protected ?string $currentToken = null;
    protected ?string $bannedReason = null;  // Motivul ban-ului activ, daca exista
    protected string $viewPath    = '';

    public function __construct()
    {
        $modelName = str_replace('Controller', 'Model', static::class);
        if (class_exists($modelName)) {
            $this->model = new $modelName();
        }

        $folder = strtolower(str_replace('Controller', '', static::class));
        $this->viewPath = ROOT . SEP . 'app' . SEP . 'views' . SEP . $folder . SEP;

        $this->resolveCurrentUser();
    }

    /**
     * Identifica user-ul curent din header Authorization, daca exista token valid.
     * Verifica si daca userul are ban activ — daca da, seteaza $bannedReason.
     */
    private function resolveCurrentUser(): void
    {
        $token = $this->extractBearerToken();
        if (!$token) return;

        $userId = (new SessionModel())->validateToken($token);
        if (!$userId) return;

        $user = (new UserModel())->findById($userId);
        if (!$user) return;

        $this->currentUser  = $user;
        $this->currentToken = $token;

        // Verifica ban activ (exceptand admin-ii — nu pot fi banati)
        if (($user['role'] ?? 'user') !== 'admin') {
            $ban = (new BansModel())->findActiveByUserId((int)$user['id']);
            if ($ban) {
                $this->bannedReason = $ban['reason'];
            }
        }
    }

    private function extractBearerToken(): ?string
    {
        // Sursa 1: $_SERVER
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        // Sursa 2: apache_request_headers (Apache strip-este uneori headerele)
        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }
        return null;
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
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Cere autentificare. Daca userul e banat, returneaza 403 cu motivul.
     * Exceptie: AuthController::me() si ::logout() nu cheama requireAuth(),
     * ci verifica direct $this->currentUser.
     */
    protected function requireAuth(): array
    {
        if ($this->currentUser === null) {
            $this->json(['error' => 'Neautentificat'], 401);
        }

        // Verifica ban — userul e logat dar banat
        if ($this->bannedReason !== null) {
            $this->json([
                'error'  => 'Contul tau este suspendat',
                'reason' => $this->bannedReason,
                'banned' => true,
            ], 403);
        }

        return $this->currentUser;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (($user['role'] ?? 'user') !== 'admin') {
            $this->json(['error' => 'Acces interzis'], 403);
        }
        return $user;
    }

    /**
     * Cere rol de organizer sau admin.
     */
    protected function requireOrganizer(): array
    {
        $user = $this->requireAuth();
        $role = $user['role'] ?? 'user';
        if ($role !== 'organizer' && $role !== 'admin') {
            $this->json(['error' => 'Necesita rol de organizer'], 403);
        }
        return $user;
    }

    protected function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = $this->viewPath . $view . '.php';
        if (!file_exists($viewFile)) {
            $this->json(['error' => "View negasit: $view"], 500);
        }
        extract($data);
        if ($layout === null) {
            include $viewFile;
            return;
        }
        ob_start();
        include $viewFile;
        $content = ob_get_clean();
        include ROOT . SEP . 'app' . SEP . 'views' . SEP . 'layouts' . SEP . $layout . '.php';
    }

    #[NoReturn]
    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit();
    }
}