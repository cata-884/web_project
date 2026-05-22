<?php

class BansModel extends Model
{
    protected string $table = 'user_bans';

    /**
     * Creeaza un ban nou pentru un user.
     * @param int $userId  — userul care primeste ban
     * @param string $reason — motivul ban-ului
     * @param int|null $days — durata in zile, NULL = permanent
     * @param int $bannedBy — admin-ul care aplica ban-ul
     * @return int — ID-ul ban-ului creat
     */
    public function createBan(int $userId, string $reason, ?int $days, int $bannedBy): int
    {
        $bannedUntil = null;
        if ($days !== null && $days > 0) {
            $bannedUntil = (new DateTimeImmutable("+{$days} days"))->format('Y-m-d H:i:s');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO user_bans (user_id, reason, banned_until, banned_by, is_active)
             VALUES (:user_id, :reason, :banned_until, :banned_by, TRUE)
             RETURNING id"
        );
        $stmt->execute([
            'user_id'      => $userId,
            'reason'       => $reason,
            'banned_until' => $bannedUntil,
            'banned_by'    => $bannedBy,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function findActiveByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, reason, banned_until, banned_by, is_active, created_at
             FROM user_bans
             WHERE user_id = :user_id
               AND is_active = TRUE
               AND (banned_until IS NULL OR banned_until > NOW())
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function liftBans(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_bans
             SET is_active = FALSE
             WHERE user_id = :user_id AND is_active = TRUE"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public function findHistoryByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.id, b.user_id, b.reason, b.banned_until, b.banned_by,
                    b.is_active, b.created_at,
                    u.username AS banned_by_username
             FROM user_bans b
             LEFT JOIN users u ON u.id = b.banned_by
             WHERE b.user_id = :user_id
             ORDER BY b.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function deleteUserSessions(int $userId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM sesiuni WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public function deleteUser(int $userId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }
}
