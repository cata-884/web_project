<?php
use JetBrains\PhpStorm\NoReturn;

class AdminController extends Controller
{
    private AdminService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AdminService(
            new UserRepository(),
            new BansRepository(),
            new CampingsRepository(),
            new ContactRepository(),
            new SessionRepository(),
        );
    }

    #[NoReturn]
    public function listUsers(): void
    {
        $this->requireAdmin();
        $limit  = min(100, max(1, (int)($_GET['limit']  ?? 20)));
        $offset = max(0,           (int)($_GET['offset'] ?? 0));
        $this->json($this->service->listUsers($_GET, $limit, $offset));
    }

    #[NoReturn]
    public function listCampings(): void
    {
        $this->requireAdmin();
        $limit  = min(50, max(1, (int)($_GET['limit']  ?? 20)));
        $offset = max(0,         (int)($_GET['offset'] ?? 0));
        $this->json($this->service->listCampings($_GET, $limit, $offset));
    }

    #[NoReturn]
    public function approveCamping(int $id): void
    {
        $this->requireAdmin();
        $this->service->approveCamping($id);
        $this->json(['ok' => true, 'message' => 'Camping aprobat si publicat.']);
    }

    #[NoReturn]
    public function rejectCamping(int $id): void
    {
        $this->requireAdmin();
        $this->service->rejectCamping($id);
        $this->json(['ok' => true, 'message' => 'Camping respins.']);
    }

    #[NoReturn]
    public function rejectCampingFeedback(int $id): void
    {
        $this->requireAdmin();
        $body = $this->getJsonBody();
        $this->service->rejectCampingFeedback($id, trim($body['feedback'] ?? ''));
        $this->json(['ok' => true, 'message' => 'Camping respins cu feedback.']);
    }

    #[NoReturn]
    public function banUser(int $id): void
    {
        $admin      = $this->requireAdmin();
        $targetUser = (new UserRepository())->findById($id);
        if (!$targetUser) throw new NotFoundException('User inexistent');
        $banId = $this->service->banUser($id, (int)$admin['id'], $this->getJsonBody(), $targetUser);
        $this->json(['ok' => true, 'message' => 'User banat cu succes', 'ban_id' => $banId, 'user_id' => $id], 201);
    }

    #[NoReturn]
    public function unbanUser(int $id): void
    {
        $this->requireAdmin();
        if (!(new UserRepository())->findById($id)) throw new NotFoundException('User inexistent');
        $lifted = $this->service->unbanUser($id);
        $this->json([
            'ok'      => true,
            'message' => $lifted > 0 ? "Au fost ridicate $lifted ban-uri active" : 'Userul nu avea ban-uri active',
            'lifted'  => $lifted,
        ]);
    }

    #[NoReturn]
    public function userBans(int $id): void
    {
        $this->requireAdmin();
        if (!(new UserRepository())->findById($id)) throw new NotFoundException('User inexistent');
        $this->json(['user_id' => $id, 'bans' => $this->service->userBans($id)]);
    }

    #[NoReturn]
    public function listMessages(): void
    {
        $this->requireAdmin();
        $this->json(['messages' => array_map([ContactDTO::class, 'fromRow'], $this->service->listMessages())]);
    }
}
