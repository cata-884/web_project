<?php
class AuthService
{
    public function __construct(
        private readonly UserRepository    $users,
        private readonly SessionRepository $sessions,
        private readonly BansRepository    $bans,
    ) {}

    public function register(array $data): array
    {
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email']    ?? '');
        $password = $data['password']      ?? '';

        if (empty($username) || empty($password) || empty($email))
            throw new ValidationException('username, email si password sunt obligatorii');
        if (strlen($username) < 3 || strlen($username) > 50)
            throw new ValidationException('Username: 3-50 caractere');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new ValidationException('Email invalid');
        if (strlen($password) < 8)
            throw new ValidationException('Parola trebuie sa aiba minim 8 caractere');
        if ($this->users->findByUsername($username))
            throw new ConflictException('Username deja folosit');
        if ($this->users->findByEmail($email))
            throw new ConflictException('Email deja folosit');

        $userId = $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT), $data['full_name'] ?? null);
        $token  = $this->sessions->create($userId);
        return ['token' => $token, 'user' => $this->users->findById($userId)];
    }

    public function login(string $identifier, string $password): array
    {
        if (empty($identifier)) throw new ValidationException('Username sau email obligatoriu');
        if (empty($password))   throw new ValidationException('Parola este obligatorie');

        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $this->users->findByEmail($identifier)
            : $this->users->findByUsername($identifier);

        if (!$user) throw new UnauthorizedException('Credentiale invalide');

        if (empty($user['password_hash']))
            throw new UnauthorizedException('Acest cont foloseste autentificare OAuth. Foloseste butonul Google.');

        if (!password_verify($password, $user['password_hash']))
            throw new UnauthorizedException('Credentiale invalide');

        $ban = $this->bans->findActiveByUserId((int)$user['id']);
        if ($ban) throw new ForbiddenException('Contul tau este suspendat: ' . $ban['reason']);

        $token = $this->sessions->create((int)$user['id']);
        unset($user['password_hash']);
        return ['token' => $token, 'user' => $user];
    }

    public function logout(string $token): void
    {
        $this->sessions->delete($token);
    }

    public function updateMe(int $userId, array $data, ?array $existingUser): array
    {
        if (isset($data['username'])) {
            $username = trim($data['username']);
            if (strlen($username) < 3 || strlen($username) > 50)
                throw new ValidationException('Username: 3-50 caractere');
            $existing = $this->users->findByUsername($username);
            if ($existing && (int)$existing['id'] !== $userId)
                throw new ConflictException('Username deja folosit');
            $data['username'] = $username;
        }
        $this->users->updateProfile($userId, $data);
        return $this->users->findById($userId);
    }

    public function changePassword(int $userId, array $data): void
    {
        $new     = trim($data['new_password']     ?? '');
        $confirm = trim($data['confirm_password'] ?? '');

        if (strlen($new) < 8)
            throw new ValidationException('Parola nouă trebuie să aibă minim 8 caractere');
        if ($new !== $confirm)
            throw new ValidationException('Parolele noi nu coincid');

        $this->users->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
    }

    public function uploadAvatar(int $userId, array $file): array
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
            throw new ValidationException('Fisier lipsa sau eroare upload');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true))
            throw new ValidationException('Format nepermis. Acceptate: JPEG, PNG, WebP, GIF');
        if ($file['size'] > 5 * 1024 * 1024)
            throw new ValidationException('Imaginea trebuie sa fie sub 5 MB');

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $dir      = ROOT . SEP . 'public' . SEP . 'uploads' . SEP . 'avatars';

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dir . SEP . $filename))
            throw new ApiException('Eroare la salvarea fisierului', 500);

        $url = '/cat/public/uploads/avatars/' . $filename;
        $this->users->updateAvatarUrl($userId, $url);
        return $this->users->findById($userId);
    }

    public function handleOAuth(string $oauthId, string $email): array
    {
        $row = $this->users->findByOauthId($oauthId);
        if ($row) {
            $userId = (int)$row['id'];
        } else {
            $existing = $this->users->findByEmail($email);
            if ($existing) {
                $this->users->linkOauth((int)$existing['id'], $oauthId);
                $userId = (int)$existing['id'];
            } else {
                $username = $this->users->generateUniqueUsername($email);
                $userId   = $this->users->createOauth($email, $username, $oauthId);
            }
        }
        $token = $this->sessions->create($userId);
        return ['token' => $token, 'user' => $this->users->findById($userId)];
    }
}
