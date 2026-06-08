<?php
class CampingDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'              => (int)$r['id'],
            'name'            => $r['name'],
            'slug'            => $r['slug'],
            'description'     => $r['description']    ?? null,
            'type'            => $r['type'],
            'region'          => $r['region']          ?? null,
            'address'         => $r['address']         ?? null,
            'latitude'        => (float)$r['latitude'],
            'longitude'       => (float)$r['longitude'],
            'price_per_night' => isset($r['price_per_night']) ? (float)$r['price_per_night'] : null,
            'capacity'        => isset($r['capacity'])  ? (int)$r['capacity']   : null,
            'rating_avg'      => isset($r['rating_avg']) ? (float)$r['rating_avg'] : null,
            'rating_count'    => (int)($r['rating_count']  ?? 0),
            'approval_status' => (int)($r['approval_status'] ?? 0),
            'admin_feedback'  => $r['admin_feedback']  ?? null,
            'facilities'      => $r['facilities']      ?? [],
            'environments'    => $r['environments']    ?? [],
            'media'           => $r['media']           ?? [],
            'created_at'      => $r['created_at']      ?? null,
        ];
    }

    public static function fromMine(array $r): array
    {
        return [
            'id'              => (int)$r['id'],
            'name'            => $r['name'],
            'slug'            => $r['slug'],
            'type'            => $r['type'],
            'region'          => $r['region']  ?? null,
            'address'         => $r['address'] ?? null,
            'approval_status' => (int)($r['approval_status'] ?? 0),
            'admin_feedback'  => $r['admin_feedback'] ?? null,
            'cover_url'       => $r['cover_url'] ?? null,
            'created_at'      => $r['created_at'] ?? null,
        ];
    }

    public static function fromRowList(array $r): array
    {
        return [
            'id'              => (int)$r['id'],
            'name'            => $r['name'],
            'slug'            => $r['slug'],
            'type'            => $r['type'],
            'region'          => $r['region'] ?? null,
            'latitude'        => (float)$r['latitude'],
            'longitude'       => (float)$r['longitude'],
            'price_per_night' => isset($r['price_per_night']) ? (float)$r['price_per_night'] : null,
            'capacity'        => isset($r['capacity']) ? (int)$r['capacity'] : null,
            'rating_avg'      => isset($r['rating_avg']) ? (float)$r['rating_avg'] : null,
            'rating_count'    => (int)($r['rating_count'] ?? 0),
            'cover_url'       => $r['cover_url'] ?? null,
            'created_at'      => $r['created_at'] ?? null,
        ];
    }
}
