<?php
class OrganizerDTO
{
    public static function fromRow(array $r): array
    {
        return [
            'id'           => (int)$r['id'],
            'user_id'      => (int)$r['user_id'],
            'legal_name'   => $r['legal_name']  ?? null,
            'cui'          => $r['cui']          ?? null,
            'status'       => $r['status'],
            'admin_notes'  => $r['admin_notes']  ?? null,
            'submitted_at' => $r['submitted_at'] ?? null,
            'reviewed_at'  => $r['reviewed_at']  ?? null,
        ];
    }
}
