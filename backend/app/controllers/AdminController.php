<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * AdminController — API pentru gestionarea utilizatorilor de catre admin.
 *
 * Functionalitati: listare useri cu filtre, ban/unban, istoric ban-uri.
 * Toate endpoint-urile necesita rol admin.
 */
class AdminController extends Controller
{
    private BansModel $bansModel;
    private UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->bansModel = new BansModel();
        $this->userModel = new UserModel();
    }

    /**
     * GET /api/admin/users
     * Listare useri cu filtre: role, banned (true/false), search (username/email).
     * Paginare: limit, offset.
     * 
     * public function listUsers(): void
     */
    #[NoReturn]
    public function listUsers(): void
    {
        $this->requireAdmin();

        $role    = $_GET['role']    ?? null;
        $banned  = $_GET['banned'] ?? null;
        $search  = $_GET['search'] ?? null;
        $limit   = min(100, max(1, (int)($_GET['limit']  ?? 20)));
        $offset  = max(0, (int)($_GET['offset'] ?? 0));

        // Construim query dinamic cu filtre
        $where  = [];
        $params = [];

        if ($role !== null && $role !== '') {
            $where[]       = "u.role = :role";
            $params['role'] = $role;
        }

        if ($search !== null && $search !== '') {
            $where[]          = "(u.username ILIKE :search OR u.email ILIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        // Filtru ban activ — subquery
        if ($banned === 'true') {
            $where[] = "EXISTS (
                SELECT 1 FROM user_bans b
                WHERE b.user_id = u.id AND b.is_active = TRUE
                  AND (b.banned_until IS NULL OR b.banned_until > NOW())
            )";
        } elseif ($banned === 'false') {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM user_bans b
                WHERE b.user_id = u.id AND b.is_active = TRUE
                  AND (b.banned_until IS NULL OR b.banned_until > NOW())
            )";
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $pdo = DB::getConnection();

        // Query principala
        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.avatar_url,
                       u.role, u.created_at
                FROM users u
                $whereClause
                ORDER BY u.created_at DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // Total count pentru paginare
        $countSql = "SELECT COUNT(*) FROM users u $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Adauga is_banned per user
        $banStmt = $pdo->prepare(
            "SELECT 1 FROM user_bans
             WHERE user_id = :uid AND is_active = TRUE
               AND (banned_until IS NULL OR banned_until > NOW())
             LIMIT 1"
        );
        foreach ($users as &$u) {
            $banStmt->execute(['uid' => $u['id']]);
            $u['is_banned'] = (bool) $banStmt->fetchColumn();
        }
        unset($u);

        $this->json([
            'users'  => $users,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * GET /api/admin/campings
     * Listare cereri de camping cu filtru dupa approval_status si paginare.
     */
    #[NoReturn]
    public function listCampings(): void
    {
        $this->requireAdmin();

        $limit  = min(50, max(1, (int)($_GET['limit']  ?? 20)));
        $offset = max(0,           (int)($_GET['offset'] ?? 0));

        $where  = [];
        $params = [];

        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $where[]          = 'c.approval_status = :status';
            $params['status'] = (int)$_GET['status'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $pdo = DB::getConnection();

        $sql = "SELECT c.id, c.name, c.slug, c.type, c.region, c.address,
                       c.price_per_night, c.capacity,
                       c.approval_status, c.admin_feedback,
                       c.is_published, c.created_at,
                       u.id          AS user_id,
                       u.username, u.email, u.full_name, u.avatar_url,
                       ov.id         AS verification_id,
                       ov.last_name, ov.first_name,
                       ov.business_type, ov.company_name, ov.registration_number,
                       ov.address_street, ov.address_number,
                       ov.address_city,  ov.address_zip,
                       ov.id_document_path, ov.registration_document_path,
                       ov.contact_phone, ov.contact_email,
                       ov.submitted_at
                FROM campings c
                JOIN users u  ON u.id  = c.created_by
                LEFT JOIN organizer_verifications ov ON ov.user_id = c.created_by
                $whereClause
                ORDER BY c.created_at DESC
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campings = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM campings c $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $this->json([
            'campings' => $campings,
            'total'    => $total,
            'limit'    => $limit,
            'offset'   => $offset,
        ]);
    }

    /**
     * POST /api/admin/campings/{id}/approve
     * Seteaza approval_status=1 si is_published=true.
     */
    #[NoReturn]
    public function approveCamping(int $id): void
    {
        $this->requireAdmin();
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare(
            "UPDATE campings SET approval_status = 1, is_published = TRUE WHERE id = :id RETURNING id"
        );
        $stmt->execute(['id' => $id]);

        if (!$stmt->fetchColumn()) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }
        $this->json(['ok' => true, 'message' => 'Camping aprobat si publicat.']);
    }

    /**
     * POST /api/admin/campings/{id}/reject
     * Seteaza approval_status=-1 si is_published=false.
     */
    #[NoReturn]
    public function rejectCamping(int $id): void
    {
        $this->requireAdmin();
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare(
            "UPDATE campings SET approval_status = -1, is_published = FALSE WHERE id = :id RETURNING id"
        );
        $stmt->execute(['id' => $id]);

        if (!$stmt->fetchColumn()) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }
        $this->json(['ok' => true, 'message' => 'Camping respins.']);
    }

    /**
     * POST /api/admin/campings/{id}/reject-feedback
     * Body JSON: { feedback: string } — seteaza approval_status=2 + admin_feedback.
     */
    #[NoReturn]
    public function rejectCampingFeedback(int $id): void
    {
        $this->requireAdmin();
        $body     = $this->getJsonBody();
        $feedback = trim($body['feedback'] ?? '');

        if (strlen($feedback) < 10) {
            $this->json(['error' => 'Feedback-ul trebuie sa contina cel putin 10 caractere.'], 400);
        }

        $pdo  = DB::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE campings
             SET approval_status = 2, is_published = FALSE, admin_feedback = :feedback
             WHERE id = :id
             RETURNING id"
        );
        $stmt->execute(['id' => $id, 'feedback' => $feedback]);

        if (!$stmt->fetchColumn()) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }
        $this->json(['ok' => true, 'message' => 'Camping respins cu feedback.']);
    }

    /**
     * POST /api/admin/users/{id}/ban
     * Body: { reason: string, days: ?int }
     *
     * Baneaza un user. Sterge sesiunile active (deconectare fortata).
     * Admin nu poate fi banat.
     */
    #[NoReturn]
    public function banUser(int $id): void
    {
        $admin = $this->requireAdmin();
        $body  = $this->getJsonBody();

        // Verifica ca userul exista
        $targetUser = $this->userModel->findById($id);
        if (!$targetUser) {
            $this->json(['error' => 'User inexistent'], 404);
        }

        // Admin nu poate fi banat
        if (($targetUser['role'] ?? 'user') === 'admin') {
            $this->json(['error' => 'Nu poti bana un administrator'], 403);
        }

        // Nu te poti bana pe tine
        if ((int)$admin['id'] === $id) {
            $this->json(['error' => 'Nu te poti bana pe tine insuti'], 400);
        }

        // Validare motiv
        $reason = trim($body['reason'] ?? '');
        if (strlen($reason) < 3) {
            $this->json(['error' => 'Motivul (reason) obligatoriu, minim 3 caractere'], 400);
        }

        // Durata: null = permanent, altfel nr de zile
        $days = isset($body['days']) ? (int)$body['days'] : null;
        if ($days !== null && $days < 1) {
            $this->json(['error' => 'Durata (days) trebuie sa fie minim 1 zi sau null pentru permanent'], 400);
        }

        // Creeaza ban-ul
        $banId = $this->bansModel->createBan($id, $reason, $days, (int)$admin['id']);

        // Deconectare fortata — sterge toate sesiunile userului
        $this->bansModel->deleteUserSessions($id);

        $this->json([
            'ok'      => true,
            'message' => 'User banat cu succes',
            'ban_id'  => $banId,
            'user_id' => $id,
            'days'    => $days,
        ], 201);
    }

    /**
     * POST /api/admin/users/{id}/unban
     * Ridica toate ban-urile active ale userului.
     */
    #[NoReturn]
    public function unbanUser(int $id): void
    {
        $this->requireAdmin();

        $targetUser = $this->userModel->findById($id);
        if (!$targetUser) {
            $this->json(['error' => 'User inexistent'], 404);
        }

        $lifted = $this->bansModel->liftBans($id);

        $this->json([
            'ok'      => true,
            'message' => $lifted > 0
                ? "Au fost ridicate $lifted ban-uri active"
                : 'Userul nu avea ban-uri active',
            'lifted'  => $lifted,
        ]);
    }

    /**
     * GET /api/admin/users/{id}/bans
     * Istoric complet de ban-uri pentru un user.
     */
    #[NoReturn]
    public function userBans(int $id): void
    {
        $this->requireAdmin();

        $targetUser = $this->userModel->findById($id);
        if (!$targetUser) {
            $this->json(['error' => 'User inexistent'], 404);
        }

        $bans = $this->bansModel->findHistoryByUserId($id);

        $this->json([
            'user_id' => $id,
            'bans'    => $bans,
        ]);
    }


 public function listMessages(): void
{
    $this->requireAdmin();
    $pdo = DB::getConnection();
    $stmt = $pdo->query("SELECT * FROM contact_requests ORDER BY created_at DESC");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $this->json(['success' => true, 'messages' => $messages]);
}

/**
     * GET /api/campings/slug/{slug}
     * Returneaza datele publice ale unui camping folosind slug-ul pentru URL-uri SEO
     */
    public function showBySlug(string $slug): void
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM campings WHERE slug = :slug AND is_published = TRUE");
        $stmt->execute(['slug' => $slug]);
        $camping = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$camping) {
            $this->json(['error' => 'Campingul nu a fost găsit sau nu este public.'], 404);
            return;
        }

        $mediaStmt = $pdo->prepare("SELECT * FROM camping_media WHERE camping_id = :id ORDER BY sort_order ASC");
        $mediaStmt->execute(['id' => $camping['id']]);
        $camping['media'] = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

        //  Returnam datele
        $this->json([
            'success' => true,
            'data' => $camping
        ]);
    }


}
