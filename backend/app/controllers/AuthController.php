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
            $this->json(['error' => 'Parola trebuie sa aiba minim 8 caractere'], 400);
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

        // Verifica ban activ inainte de a crea sesiune
        $ban = (new BansModel())->findActiveByUserId((int)$user['id']);
        if ($ban) {
            $this->json([
                'error'  => 'Contul tau este suspendat',
                'reason' => $ban['reason'],
                'banned' => true,
                'banned_until' => $ban['banned_until'],
            ], 403);
        }

        try {
            $token = $this->sessionModel->create((int)$user['id']);
        } catch (Exception $e) {
            $this->json(['error' => 'Eroare la creare sesiune: ' . $e->getMessage()], 500);
        }

        unset($user['password_hash']);

        $this->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * POST /api/auth/logout
     * Header: Authorization: Bearer {token}
     *
     * NU cheama requireAuth() — userul banat trebuie sa poata face logout.
     */
    #[NoReturn]
    public function logout(): void
    {
        if ($this->currentUser === null) {
            $this->json(['error' => 'Neautentificat'], 401);
        }
        $this->sessionModel->delete($this->currentToken);
        $this->json(['ok' => true, 'message' => 'Deconectat cu succes']);
    }

    /**
     * GET /api/auth/me
     * Header: Authorization: Bearer {token}
     *
     * NU cheama requireAuth() — userul banat trebuie sa poata vedea de ce e banat.
     * Include informatii despre ban in raspuns daca userul e banat.
     */
    #[NoReturn]
    public function me(): void
    {
        if ($this->currentUser === null) {
            $this->json(['error' => 'Neautentificat'], 401);
        }

        $response = ['user' => $this->currentUser];

        // Daca userul e banat, adauga informatiile de ban
        if ($this->bannedReason !== null) {
            $ban = (new BansModel())->findActiveByUserId((int)$this->currentUser['id']);
            $response['banned'] = true;
            $response['ban'] = [
                'reason'       => $ban['reason'] ?? $this->bannedReason,
                'banned_until' => $ban['banned_until'] ?? null,
                'created_at'   => $ban['created_at'] ?? null,
            ];
        }

        $this->json($response);
    }

    /**
     * PATCH /api/users/me
     * Body: { full_name?, username? }
     */
    #[NoReturn]
    public function updateMe(): void
    {
        $user = $this->requireAuth();
        $body = $this->getJsonBody();

        if (empty($body)) {
            $this->json(['error' => 'Body gol'], 400);
        }

        if (isset($body['username'])) {
            $username = trim($body['username']);
            if (strlen($username) < 3 || strlen($username) > 50) {
                $this->json(['error' => 'Username: 3-50 caractere'], 400);
            }
            $existing = $this->userModel->findByUsername($username);
            if ($existing && (int)$existing['id'] !== (int)$user['id']) {
                $this->json(['error' => 'Username deja folosit'], 409);
            }
            $body['username'] = $username;
        }

        $this->userModel->updateProfile((int)$user['id'], $body);
        $updated = $this->userModel->findById((int)$user['id']);
        $this->json(['user' => $updated]);
    }
}
