<?php
class ReviewDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'         => (int)$r['id'],
            'user_id'    => (int)$r['user_id'],
            'camping_id' => (int)$r['camping_id'],
            'booking_id' => isset($r['booking_id']) ? (int)$r['booking_id'] : null,
            'rating'     => (int)$r['rating'],
            'content'    => $r['content'] ?? null,
            'created_at' => $r['created_at'] ?? null,
            'author'     => [
                'username'   => $r['username']   ?? null,
                'avatar_url' => $r['avatar_url'] ?? null,
            ],
            'media'      => $r['media'] ?? [],
        ];
    }
}
