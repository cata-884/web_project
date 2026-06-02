<?php
class ContactDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'         => (int)$r['id'],
            'name'       => $r['name'],
            'email'      => $r['email'],
            'phone'      => $r['phone']   ?? null,
            'message'    => $r['message'],
            'created_at' => $r['created_at'] ?? null,
        ];
    }
}
