<?php

class SessionModel extends Model
{
    protected string $table = 'sesiuni';
    private const TTL_HOURS = 24;

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function create(int $userId): string
    {
        //probabil ca o sa sterg comentariul, DAR,
        //se genereaza sir de 32 de bytes, convertiti in hexa
        $token     = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+' . self::TTL_HOURS . ' hours'))
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT INTO sesiuni (token, user_id, expires_at)
             VALUES (:token, :user_id, :expires_at)"
        );
        $stmt->execute([
            'token'      => $token,
            'user_id'    => $userId,
            'expires_at' => $expiresAt,
        ]);
        return $token;
    }

    public function validateToken(string $token): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM sesiuni
             WHERE token = :token AND expires_at > NOW()"
        );
        $stmt->execute(['token' => $token]);
        $userId = $stmt->fetchColumn();
        return $userId ? (int) $userId : null;
    }

    public function delete(string $token): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM sesiuni WHERE token = :token");
        $stmt->execute(['token' => $token]);
    }

    public function cleanupExpired(): int
    {
        return (int) $this->pdo->exec("DELETE FROM sesiuni WHERE expires_at < NOW()");
    }
}