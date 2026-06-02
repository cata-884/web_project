<?php
class BookingDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'          => (int)$r['id'],
            'camping_id'  => (int)$r['camping_id'],
            'user_id'     => (int)$r['user_id'],
            'check_in'    => $r['check_in'],
            'check_out'   => $r['check_out'],
            'guests'      => (int)$r['guests'],
            'status'      => $r['status'],
            'total_price' => isset($r['total_price']) ? (float)$r['total_price'] : null,
            'camping'     => [
                'name'   => $r['camping_name']   ?? null,
                'slug'   => $r['camping_slug']   ?? null,
                'region' => $r['camping_region'] ?? null,
            ],
            'created_at'  => $r['created_at'] ?? null,
        ];
    }
}
