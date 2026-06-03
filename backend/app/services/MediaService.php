<?php
class MediaService
{
    private const UPLOAD_DIR = 'uploads';

    private const ALLOWED_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'audio' => [
            'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/x-ogg',
            'audio/wav', 'audio/x-wav', 'audio/wave',
            'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/webm', 'audio/aac',
        ],
        'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                    'video/x-msvideo', 'video/x-matroska'],
    ];

    private const MAX_SIZES = ['image' => 10485760, 'audio' => 20971520, 'video' => 52428800];

    private const AUDIO_EXTENSIONS = ['mp3', 'ogg', 'wav', 'm4a', 'aac', 'webm'];

    public function __construct(
        private readonly MediaRepository    $media,
        private readonly CampingsRepository $campings,
        private readonly ReviewsRepository  $reviews,
    ) {}

    public function uploadForCamping(int $campingId, array $user, array $file): array
    {
        $ownerId = $this->campings->getOwnerId($campingId);
        if ($ownerId === null) throw new NotFoundException('Camping inexistent');
        if (($user['role'] ?? 'user') !== 'admin' && (int)$user['id'] !== $ownerId)
            throw new ForbiddenException('Nu ai drepturi pe acest camping');

        [$validFile, $mediaType] = $this->validate($file);
        $filename = $this->saveFile($validFile, $campingId);
        $url      = '/cat/public/' . self::UPLOAD_DIR . '/campings/' . $campingId . '/' . $filename;
        $id       = $this->media->createForCamping($campingId, $mediaType, $url);

        return ['id' => $id, 'type' => $mediaType, 'url' => $url];
    }

    public function uploadForReview(int $reviewId, array $user, array $file): array
    {
        $ownerId = $this->reviews->getOwnerId($reviewId);
        if ($ownerId === null) throw new NotFoundException('Recenzie inexistenta');
        if ((int)$user['id'] !== $ownerId) throw new ForbiddenException('Nu ai drepturi pe aceasta recenzie');

        [$validFile, $mediaType] = $this->validate($file);
        $binary = file_get_contents($validFile['tmp_name']);
        if ($binary === false) throw new ApiException('Eroare la citirea fisierului', 500);
        $id = $this->media->createForReview($reviewId, $mediaType, $binary);

        return ['id' => $id, 'type' => $mediaType];
    }

    public function deleteCampingMedia(int $id, array $user): void
    {
        $ownerId = $this->media->getCampingMediaOwner($id);
        if ($ownerId === null) throw new NotFoundException('Media inexistent');
        if (($user['role'] ?? 'user') !== 'admin' && (int)$user['id'] !== $ownerId)
            throw new ForbiddenException('Nu ai drepturi');
        $this->media->deleteCampingMedia($id);
    }

    public function deleteReviewMedia(int $id, array $user): void
    {
        $ownerId = $this->media->getReviewMediaOwner($id);
        if ($ownerId === null) throw new NotFoundException('Media inexistent');
        if (($user['role'] ?? 'user') !== 'admin' && (int)$user['id'] !== $ownerId)
            throw new ForbiddenException('Nu ai drepturi');
        $this->media->deleteReviewMedia($id);
    }

    public function getReviewMediaData(int $id): array
    {
        $row = $this->media->getReviewMediaData($id);
        if (!$row) throw new NotFoundException('Media inexistent');
        return $row;
    }

    private function validate(array $file): array
    {
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK)
            throw new ValidationException('Fisier lipsa sau eroare upload');

        $mime      = $this->detectMime($file['tmp_name']);
        $mediaType = $this->detectType($mime);

        if (!$mediaType) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, self::AUDIO_EXTENSIONS, true)) $mediaType = 'audio';
        }
        if (!$mediaType)
            throw new ValidationException('Tip fisier nepermis (' . $mime . '). Acceptate: JPEG, PNG, WebP, GIF, MP3, OGG, WAV, MP4, WebM');
        if ($file['size'] > self::MAX_SIZES[$mediaType])
            throw new ValidationException('Fisier prea mare. Max: ' . (self::MAX_SIZES[$mediaType] / 1048576) . ' MB');

        return [$file, $mediaType];
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $path);
            finfo_close($fi);
            if ($mime) return $mime;
        }
        return mime_content_type($path) ?: '';
    }

    private function detectType(string $mime): ?string
    {
        foreach (self::ALLOWED_TYPES as $type => $mimes) {
            if (in_array($mime, $mimes, true)) return $type;
        }
        return null;
    }

    private function saveFile(array $file, int $entityId): string
    {
        $ext      = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin'));
        $filename = uniqid('media_', true) . '.' . $ext;
        $dir      = ROOT . SEP . 'public' . SEP . self::UPLOAD_DIR . SEP . 'campings' . SEP . $entityId;

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dir . SEP . $filename))
            throw new ApiException('Eroare la salvarea fisierului', 500);

        return $filename;
    }
}
