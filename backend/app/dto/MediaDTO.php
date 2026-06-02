<?php
class MediaDTO
{
    public static function fromCampingRow(array $r): array
    {
        return [
            'id'         => (int)$r['id'],
            'camping_id' => (int)$r['camping_id'],
            'type'       => $r['type'],
            'url'        => $r['url'],
            'created_at' => $r['created_at'] ?? null,
        ];
    }

    public static function fromReviewRow(array $r): array
    {
        return [
            'id'         => (int)$r['id'],
            'review_id'  => (int)$r['review_id'],
            'type'       => $r['type'],
            'created_at' => $r['created_at'] ?? null,
        ];
    }
}
