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
    
    /**
     * Start Google OAuth flow
     * face redirect spre 
     * 
     * https://accounts.google.com/o/oauth2/v2/auth
     *  ?client_id=...&redirect_uri=...&response_type=code&scope=email%20profile
     * 
     * GET /api/auth/oauth/google/start
     */
    #[NoReturn]
    public function oauthGoogleStart(): void
    {   
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?client_id="
            . urlencode(GOOGLE_CLIENT_ID)
            . "&redirect_uri=" . urlencode(GOOGLE_REDIRECT_URI)
            . "&response_type=code"
            . "&scope=" . urlencode("openid email profile")
            . "&state=" . urlencode($state)
            . "&prompt=select_account";

        header("Location: " . $googleAuthUrl);
        exit();
    }

    /**
     * GET /api/auth/oauth/google/callback?code=...&state=...
     *
     * primeste `?code=...`, face POST la `https://oauth2.googleapis.com/token`
     *  pentru access_token, apoi GET la `https://www.googleapis.com/oauth2/v2/userinfo`
     *  pentru date user. 
     *  Cauta user dupa `oauth_id`, creeaza-l daca lipseste,
     *   genereaza sesiune, redirect spre frontend cu tokenul in URL 
     *  (sau seteaza cookie).
     */
    #[NoReturn]
    public function oauthGoogleCallback(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verifica state (CSRF protection)
        $receivedState = $_GET['state'] ?? '';
        $savedState    = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']); 

        if (!$receivedState || !hash_equals($savedState, $receivedState)) {
            $this->redirectToAuthError('invalid_state');
        }

        // Userul a refuzat la Google, sau Google a returnat eroare
        if (isset($_GET['error'])) {
            $this->redirectToAuthError($_GET['error']);
        }

        // Verifica prezenta code-ului
        if (empty($_GET['code'])) {
            $this->redirectToAuthError('missing_code');
        }
        $code = $_GET['code'];

        // Schimba code-ul pe access_token (POST server-to-server)
        $tokenResponse = $this->httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($tokenResponse['access_token'])) {
            $this->redirectToAuthError('token_exchange_failed');
        }

        // Ia datele user-ului
        $googleUser = $this->httpGet(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            ['Authorization: Bearer ' . $tokenResponse['access_token']]
        );

        if (empty($googleUser['id']) || empty($googleUser['email'])) {
            $this->redirectToAuthError('userinfo_failed');
        }

        if (empty($googleUser['verified_email'])) {
            $this->redirectToAuthError('email_not_verified');
        }

        // Gaseste / creeaza userul in DB
        $userId = $this->userModel->findOrCreateOauthUser(
            'google',
            $googleUser['id'],
            $googleUser['email']
        );

        $token = $this->sessionModel->create($userId);

        header('Location: http://localhost/pages/auth.html?token=' . urlencode($token));
        exit();
    }


    #[NoReturn]
private function redirectToAuthError(string $code): void
{
    header('Location: http://localhost/pages/auth.html?error=' . urlencode($code));
    exit();
}

private function httpPost(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

private function httpGet(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

}
