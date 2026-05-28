<?php

use JetBrains\PhpStorm\NoReturn;

class PreferencesController extends Controller
{
    private const VALID_TYPES  = ['wild', 'glamping', 'rv', 'tent', 'cabin'];
    private const VALID_STYLES = ['solo', 'couple', 'family', 'group', 'pets'];
    private const VALID_ZONES  = ['mountain', 'seaside', 'delta', 'forest_lake'];

    #[NoReturn]
    public function get(): void
    {
        $user = $this->requireAuth();
        $row  = $this->model->getByUser((int) $user['id']);

        if (!$row) {
            $this->json(['camping_types' => [], 'travel_styles' => [], 'preferred_zones' => []]);
        }

        $this->json($this->model->decode($row));
    }

    #[NoReturn]
    public function save(): void
    {
        $user = $this->requireAuth();
        $body = $this->getJsonBody();

        $filter = fn(array $val, array $allowed) =>
            array_values(array_filter((array) $val, fn($v) => in_array($v, $allowed, true)));

        $types  = $filter($body['camping_types']   ?? [], self::VALID_TYPES);
        $styles = $filter($body['travel_styles']   ?? [], self::VALID_STYLES);
        $zones  = $filter($body['preferred_zones'] ?? [], self::VALID_ZONES);

        $this->model->upsert((int) $user['id'], $types, $styles, $zones);
        $this->json(['ok' => true]);
    }
}
