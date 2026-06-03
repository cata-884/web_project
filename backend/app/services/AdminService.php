<?php
class AdminService
{
    public function __construct(
        private readonly UserRepository     $users,
        private readonly BansRepository     $bans,
        private readonly CampingsRepository $campings,
        private readonly ContactRepository  $contact,
        private readonly SessionRepository  $sessions,
    ) {}

    public function listUsers(array $filters, int $limit, int $offset): array
    {
        $parsed = ['role' => $filters['role'] ?? null, 'search' => $filters['search'] ?? null];
        if (isset($filters['banned'])) $parsed['banned'] = $filters['banned'] === 'true';

        $userList = $this->users->findWithBanStatus($parsed, $limit, $offset);
        $total    = $this->users->countWithBanStatus($parsed);

        foreach ($userList as &$u) {
            $u['is_banned'] = (bool)$this->bans->findActiveByUserId((int)$u['id']);
        }
        return ['users' => $userList, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function listCampings(array $filters, int $limit, int $offset): array
    {
        return [
            'campings' => $this->campings->findForAdmin($filters, $limit, $offset),
            'total'    => $this->campings->countForAdmin($filters),
            'limit'    => $limit,
            'offset'   => $offset,
        ];
    }

    public function approveCamping(int $id): void
    {
        if (!$this->campings->approve($id)) throw new NotFoundException('Camping inexistent');
    }

    public function rejectCamping(int $id): void
    {
        if (!$this->campings->reject($id)) throw new NotFoundException('Camping inexistent');
    }

    public function rejectCampingFeedback(int $id, string $feedback): void
    {
        if (strlen($feedback) < 10) throw new ValidationException('Feedback-ul trebuie sa contina cel putin 10 caractere.');
        if (!$this->campings->rejectWithFeedback($id, $feedback)) throw new NotFoundException('Camping inexistent');
    }

    public function banUser(int $targetId, int $adminId, array $data, array $targetUser): int
    {
        if (($targetUser['role'] ?? 'user') === 'admin') throw new ForbiddenException('Nu poti bana un administrator');
        if ($adminId === $targetId) throw new ValidationException('Nu te poti bana pe tine insuti');
        $reason = trim($data['reason'] ?? '');
        if (strlen($reason) < 3) throw new ValidationException('Motivul (reason) obligatoriu, minim 3 caractere');
        $days = isset($data['days']) ? (int)$data['days'] : null;
        if ($days !== null && $days < 1) throw new ValidationException('Durata (days) trebuie sa fie minim 1 zi sau null pentru permanent');
        $banId = $this->bans->create($targetId, $reason, $days, $adminId);
        $this->sessions->deleteByUserId($targetId);
        return $banId;
    }

    public function unbanUser(int $userId): int
    {
        return $this->bans->liftBans($userId);
    }

    public function userBans(int $userId): array
    {
        return $this->bans->findHistoryByUserId($userId);
    }

    public function listMessages(): array
    {
        return $this->contact->findAll();
    }
}
