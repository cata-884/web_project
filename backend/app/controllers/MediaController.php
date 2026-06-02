<?php
use JetBrains\PhpStorm\NoReturn;

class MediaController extends Controller
{
    private MediaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new MediaService(
            new MediaRepository(),
            new CampingsRepository(),
            new ReviewsRepository(),
        );
    }

    #[NoReturn]
    public function uploadCampingMedia(int $campingId): void
    {
        $user   = $this->requireAuth();
        $result = $this->service->uploadForCamping($campingId, $user, $_FILES['file'] ?? []);
        $this->json(['media' => MediaDTO::fromCampingRow(array_merge($result, ['camping_id' => $campingId]))], 201);
    }

    #[NoReturn]
    public function uploadReviewMedia(int $reviewId): void
    {
        $user   = $this->requireAuth();
        $result = $this->service->uploadForReview($reviewId, $user, $_FILES['file'] ?? []);
        $this->json(['media' => array_merge(MediaDTO::fromReviewRow(array_merge($result, ['review_id' => $reviewId])), ['id' => $result['id']])], 201);
    }

    public function serveReviewMedia(int $id): void
    {
        $row = $this->service->getReviewMediaData($id);
        $contentType = match($row['type']) {
            'audio' => 'audio/mpeg',
            'video' => 'video/mp4',
            default => 'image/jpeg',
        };
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($row['data']));
        header('Cache-Control: public, max-age=3600');
        echo $row['data'];
        exit();
    }

    #[NoReturn]
    public function destroyCampingMedia(int $id): void
    {
        $user = $this->requireAuth();
        $this->service->deleteCampingMedia($id, $user);
        $this->json(['ok' => true]);
    }

    #[NoReturn]
    public function destroyReviewMedia(int $id): void
    {
        $user = $this->requireAuth();
        $this->service->deleteReviewMedia($id, $user);
        $this->json(['ok' => true]);
    }
}
