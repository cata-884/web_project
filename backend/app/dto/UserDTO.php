<?php
class UserDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'         => (int)$r['id'],
            'username'   => $r['username'],
            'email'      => $r['email'],
            'full_name'  => $r['full_name']  ?? null,
            'avatar_url' => $r['avatar_url'] ?? null,
            'role'       => $r['role'],
            'is_oauth'   => (bool)($r['is_oauth'] ?? false),
            'created_at' => $r['created_at'] ?? null,
        ];
    }
}
