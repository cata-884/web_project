<?php
class SectionDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'             => (int)$r['id'],
            'user_id'        => (int)$r['user_id'],
            'name'           => $r['name'],
            'campings_count' => isset($r['campings_count']) ? (int)$r['campings_count'] : 0,
            'created_at'     => $r['created_at'] ?? null,
        ];
    }
}
