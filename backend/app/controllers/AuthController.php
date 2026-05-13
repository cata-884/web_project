<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * Endpoint-uri: register, login, logout, me.
 * Foloseste UserModel + SessionModel direct
 */
class AuthController extends Controller
{
    private UserModel $userModel;
    private SessionModel $sessionModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel    = new UserModel();
        $this->sessionModel = new SessionModel();
    }

    /**
     * POST /api/auth/register
     * Body: { username, email, password, full_name? }
     *
     * Creeaza un cont nou cu parola hashed.
     * Returneaza token de sesiune direct.
     */
    #[NoReturn]
    public function register(): void
    {
        $data = $this->getJsonBody();

        if (empty($data['username']) || empty($data['password']) || empty($data['email'])) {
            $this->json(['error' => 'username, email si password sunt obligatorii'], 400);
        }

        $username = trim($data['username']);
        $email    = trim($data['email']);
        $password = $data['password'];

        if (strlen($username) < 3 || strlen($username) > 50) {
            $this->json(['error' => 'Username: 3-50 caractere'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Email invalid'], 400);
        }
        if (strlen($password) < 8) {
            $this->json(['error' => 'Parola trebuie să aibă minim 8 caractere'], 400);
        }

        if ($this->userModel->findByUsername($username)) {
            $this->json(['error' => 'Username deja folosit'], 409);
        }
        if ($this->userModel->findByEmail($email)) {
            $this->json(['error' => 'Email deja folosit'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $userId = $this->userModel->create(
                $username,
                $email,
                $hash,
                $data['full_name'] ?? null
            );

            $token = $this->sessionModel->create($userId);
            $user  = $this->userModel->findById($userId);

            $this->json([
                'message' => 'Cont creat cu succes',
                'token'   => $token,
                'user'    => $user,
            ], 201);
        } catch (Exception $e) {
            $this->json(['error' => 'Eroare la creare: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/auth/login
     * Body: { username, password }  sau  { email, password }
     *
     * Verifica credentialele si returneaza un Bearer token.
     */
    #[NoReturn]
    public function login(): void
    {
        $data = $this->getJsonBody();

        $password = $data['password'] ?? '';
        if (empty($password)) {
            $this->json(['error' => 'Parola este obligatorie'], 400);
        }

        // Acceptare username SAU email
        $identifier = trim($data['username'] ?? $data['email'] ?? '');
        if (empty($identifier)) {
            $this->json(['error' => 'Username sau email obligatoriu'], 400);
        }

        // Cautam userul
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $this->userModel->findByEmail($identifier)
            : $this->userModel->findByUsername($identifier);

        if (!$user) {
            $this->json(['error' => 'Credentiale invalide'], 401);
        }

        if (empty($user['password_hash'])) {
            $this->json(['error' => 'Acest cont foloseste autentificare OAuth. Foloseste butonul Google.'], 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->json(['error' => 'Credentiale invalide'], 401);
        }

        $token = $this->sessionModel->create((int)$user['id']);

        unset($user['password_hash']);

        $this->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * POST /api/auth/logout
     * Header: Authorization: Bearer {token}
     */
    #[NoReturn]
    public function logout(): void
    {
        $this->requireAuth();
        $this->sessionModel->delete($this->currentToken);
        $this->json(['ok' => true, 'message' => 'Deconectat cu succes']);
    }

    /**
     * GET /api/auth/me
     * Header: Authorization: Bearer {token}
     */
    #[NoReturn]
    public function me(): void
    {
        $user = $this->requireAuth();
        $this->json(['user' => $user]);
    }
}
