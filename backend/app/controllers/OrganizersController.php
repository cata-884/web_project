<?php
use JetBrains\PhpStorm\NoReturn;

class OrganizersController extends Controller
{
    private OrganizersService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new OrganizersService(new OrganizersRepository());
    }

    #[NoReturn]
    public function uploadDocument(): void
    {
        $this->requireAuth();
        $file = $_FILES['file'] ?? [];
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
            throw new ValidationException('Fisier lipsa sau eroare upload');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true))
            throw new ValidationException('Format nepermis. Acceptate: JPEG, PNG, WebP, PDF');
        if ($file['size'] > 10 * 1024 * 1024)
            throw new ValidationException('Fisierul trebuie sa fie sub 10 MB');

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
        $filename = uniqid('doc_', true) . '.' . $ext;
        $dir      = ROOT . SEP . 'public' . SEP . 'uploads' . SEP . 'documents';

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dir . SEP . $filename))
            throw new ApiException('Eroare la salvarea fisierului', 500);

        $this->json(['url' => '/cat/public/uploads/documents/' . $filename], 201);
    }

    #[NoReturn]
    public function apply(): void
    {
        $user = $this->requireAuth();
        $app  = $this->service->apply((int)$user['id'], $user['role'] ?? 'user', $this->getJsonBody());
        $this->json(['application' => OrganizerDTO::fromRow($app)], 201);
    }

    #[NoReturn]
    public function myApplication(): void
    {
        $user = $this->requireAuth();
        $this->json(['application' => OrganizerDTO::fromRow($this->service->myApplication((int)$user['id']))]);
    }

}
