<?php
use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;

class AuthController extends Controller
{
    private AuthService $service;
    private GoogleOAuthClient $google;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AuthService(new UserRepository(), new SessionRepository(), new BansRepository());
        $this->google  = new GoogleOAuthClient();
    }

    #[NoReturn]
    public function register(): void
    {
        $result = $this->service->register($this->getJsonBody());
        $this->json(['message' => 'Cont creat cu succes', 'token' => $result['token'], 'user' => UserDTO::fromRow($result['user'])], 201);
    }

    #[NoReturn]
    public function login(): void
    {
        $data       = $this->getJsonBody();
        $identifier = trim($data['username'] ?? $data['email'] ?? '');
        $result     = $this->service->login($identifier, $data['password'] ?? '');
        $this->json(['token' => $result['token'], 'user' => UserDTO::fromRow($result['user'])]);
    }

    #[NoReturn]
    public function logout(): void
    {
        if ($this->currentUser === null) throw new UnauthorizedException();
        $this->service->logout($this->currentToken);
        $this->json(['ok' => true, 'message' => 'Deconectat cu succes']);
    }

    #[NoReturn]
    public function me(): void
    {
        if ($this->currentUser === null) throw new UnauthorizedException();

        $response = ['user' => UserDTO::fromRow($this->currentUser)];
        if ($this->bannedReason !== null) {
            $ban = (new BansRepository())->findActiveByUserId((int)$this->currentUser['id']);
            $response['banned'] = true;
            $response['ban']    = [
                'reason'       => $ban['reason']       ?? $this->bannedReason,
                'banned_until' => $ban['banned_until'] ?? null,
                'created_at'   => $ban['created_at']   ?? null,
            ];
        }
        $this->json($response);
    }

    #[NoReturn]
    public function changePassword(): void
    {
        $user = $this->requireAuth();
        $this->service->changePassword((int)$user['id'], $this->getJsonBody());
        $this->json(['ok' => true, 'message' => 'Parola a fost schimbată cu succes']);
    }

    #[NoReturn]
    public function uploadAvatar(): void
    {
        $user    = $this->requireAuth();
        $updated = $this->service->uploadAvatar((int)$user['id'], $_FILES['file'] ?? []);
        $this->json(['user' => UserDTO::fromRow($updated)]);
    }

    #[NoReturn]
    public function updateMe(): void
    {
        $user    = $this->requireAuth();
        $updated = $this->service->updateMe((int)$user['id'], $this->getJsonBody(), $user);
        $this->json(['user' => UserDTO::fromRow($updated)]);
    }

    /** @throws RandomException */
    #[NoReturn]
    public function oauthGoogleStart(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        header("Location: https://accounts.google.com/o/oauth2/v2/auth?client_id=" . urlencode(GOOGLE_CLIENT_ID)
             . "&redirect_uri=" . urlencode(GOOGLE_REDIRECT_URI)
             . "&response_type=code&scope=" . urlencode("openid email profile")
             . "&state=" . urlencode($state) . "&prompt=select_account");
        exit();
    }

    /** @throws Exception */
    #[NoReturn]
    public function oauthGoogleCallback(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $receivedState = $_GET['state'] ?? '';
        $savedState    = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);

        if (!$receivedState || !hash_equals($savedState, $receivedState)) $this->redirectToAuthError('invalid_state');
        if (isset($_GET['error']))  $this->redirectToAuthError($_GET['error']);
        if (empty($_GET['code']))   $this->redirectToAuthError('missing_code');

        $tokenResponse = $this->google->exchangeCode($_GET['code']);
        if (empty($tokenResponse['access_token'])) $this->redirectToAuthError('token_exchange_failed');

        $googleUser = $this->google->getUserInfo($tokenResponse['access_token']);
        if (empty($googleUser['id']) || empty($googleUser['email'])) $this->redirectToAuthError('userinfo_failed');
        if (empty($googleUser['verified_email'])) $this->redirectToAuthError('email_not_verified');

        $result = $this->service->handleOAuth($googleUser['id'], $googleUser['email']);
        header('Location: http://localhost/pages/auth.html?token=' . urlencode($result['token']));
        exit();
    }

    #[NoReturn]
    private function redirectToAuthError(string $code): void
    {
        header('Location: http://localhost/pages/auth.html?error=' . urlencode($code));
        exit();
    }
}
