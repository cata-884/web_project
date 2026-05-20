<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * MediaController — upload si stergere fisiere multimedia.
 * Suporta imagini, audio si video pentru campinguri si recenzii.
 */
class MediaController extends Controller
{
    private const UPLOAD_DIR = 'uploads';

    private const ALLOWED_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'audio' => ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4'],
        'video' => ['video/mp4', 'video/webm', 'video/ogg'],
    ];

    private const MAX_SIZES = [
        'image' => 10 * 1024 * 1024,   // 10 MB
        'audio' => 5  * 1024 * 1024,   // 5 MB
        'video' => 50 * 1024 * 1024,   // 50 MB
    ];

    /**
     * POST /api/campings/{campingId}/media
     * Upload fisier multimedia pentru un camping (multipart/form-data)
     */
    #[NoReturn]
    public function uploadCampingMedia(int $campingId): void
    {
        $user = $this->requireAuth();

        $campingModel = new CampingsModel();
        $ownerId = $campingModel->getOwnerId($campingId);
        if ($ownerId === null) {
            $this->json(['error' => 'Camping inexistent'], 404);
        }
        if (($user['role'] ?? 'user') !== 'admin' && (int)$user['id'] !== $ownerId) {
            $this->json(['error' => 'Nu ai drepturi pe acest camping'], 403);
        }

        $file = $this->validateUpload();

        $mediaType = $this->detectMediaType($file['type']);
        $filename  = $this->saveFile($file, 'campings', $campingId);
        $url       = '/' . self::UPLOAD_DIR . '/campings/' . $campingId . '/' . $filename;

        $sortOrder = $this->model->nextSortOrder($campingId);
        $id = $this->model->createForCamping($campingId, $mediaType, $url, $sortOrder);

        $this->json([
            'media' => [
                'id'   => $id,
                'type' => $mediaType,
                'url'  => $url,
            ]
        ], 201);
    }

    /**
     * POST /api/reviews/{reviewId}/media
     * Upload fisier multimedia pentru o recenzie
     */
    #[NoReturn]
    public function uploadReviewMedia(int $reviewId): void
    {
        $user = $this->requireAuth();

        $reviewsModel = new ReviewsModel();
        $ownerId = $reviewsModel->getOwnerId($reviewId);
        if ($ownerId === null) {
            $this->json(['error' => 'Recenzie inexistenta'], 404);
        }
        if ((int)$user['id'] !== $ownerId) {
            $this->json(['error' => 'Nu ai drepturi pe aceasta recenzie'], 403);
        }

        $file = $this->validateUpload();

        $mediaType = $this->detectMediaType($file['type']);
        $filename  = $this->saveFile($file, 'reviews', $reviewId);
        $url       = '/' . self::UPLOAD_DIR . '/reviews/' . $reviewId . '/' . $filename;

        $id = $this->model->createForReview($reviewId, $mediaType, $url);

        $this->json([
            'media' => [
                'id'   => $id,
                'type' => $mediaType,
                'url'  => $url,
            ]
        ], 201);
    }

    /**
     * DELETE /api/media/camping/{id}
     * Sterge un fisier media al unui camping
     */
    #[NoReturn]
    public function destroyCampingMedia(int $id): void
    {
        $user = $this->requireAuth();

        $ownerId = $this->model->getCampingMediaOwner($id);
        if ($ownerId === null) {
            $this->json(['error' => 'Media inexistent'], 404);
        }
        if (($user['role'] ?? 'user') !== 'admin' && (int)$user['id'] !== $ownerId) {
            $this->json(['error' => 'Nu ai drepturi'], 403);
        }

        $this->model->deleteCampingMedia($id);
        $this->json(['ok' => true]);
    }

    /**
     * DELETE /api/media/review/{id}
     * Sterge un fisier media al unei recenzii
     */
    #[NoReturn]
    public function destroyReviewMedia(int $id): void
    {
        $user = $this->requireAuth();

        $ownerId = $this->model->getReviewMediaOwner($id);
        if ($ownerId === null) {
            $this->json(['error' => 'Media inexistent'], 404);
        }
        if (($user['role'] ?? 'user') !== 'admin' && (int)$user['id'] !== $ownerId) {
            $this->json(['error' => 'Nu ai drepturi'], 403);
        }

        $this->model->deleteReviewMedia($id);
        $this->json(['ok' => true]);
    }

    /**
     * Valideaza fisierul uploadat din $_FILES['file']
     */
    private function validateUpload(): array
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Fisier lipsa sau eroare upload'], 400);
        }

        $file = $_FILES['file'];
        $mime = $file['type'];

        $mediaType = $this->detectMediaType($mime);
        if (!$mediaType) {
            $this->json([
                'error' => 'Tip fisier nepermis. Acceptate: JPEG, PNG, WebP, GIF, MP3, OGG, WAV, MP4, WebM'
            ], 400);
        }

        $maxSize = self::MAX_SIZES[$mediaType];
        if ($file['size'] > $maxSize) {
            $this->json([
                'error' => 'Fisier prea mare. Max: ' . ($maxSize / 1024 / 1024) . ' MB'
            ], 400);
        }

        return $file;
    }

    /**
     * Detecteaza tipul media (image/audio/video) din MIME type
     */
    private function detectMediaType(string $mime): ?string
    {
        foreach (self::ALLOWED_TYPES as $type => $mimes) {
            if (in_array($mime, $mimes, true)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Salveaza fisierul pe disc. Returneaza numele generat.
     */
    private function saveFile(array $file, string $category, int $entityId): string
    {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
        $filename = uniqid('media_', true) . '.' . $ext;

        $dir = ROOT . SEP . 'public' . SEP . self::UPLOAD_DIR
             . SEP . $category . SEP . $entityId;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $destination = $dir . SEP . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->json(['error' => 'Eroare la salvarea fisierului'], 500);
        }

        return $filename;
    }
}
