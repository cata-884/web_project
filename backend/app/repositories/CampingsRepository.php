<?php
class CampingsRepository extends Repository
{
    public function search(array $filters = []): array
    {
        [$where, $params] = $this->buildSearchConditions($filters);
        $limit  = min(100, max(1, (int)($filters['limit']  ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT id, name, slug, type, region, latitude, longitude,
                       price_per_night, capacity, rating_avg, rating_count, created_at
                FROM campings WHERE $where
                ORDER BY rating_avg DESC NULLS LAST, created_at DESC
                LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countSearch(array $filters = []): int
    {
        [$where, $params] = $this->buildSearchConditions($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(id) FROM campings WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildSearchConditions(array $filters): array
    {
        $conditions = ['approval_status = 1'];
        $params     = [];

        if (!empty($filters['region'])) {
            $conditions[] = 'region = :region';
            $params['region'] = $filters['region'];
        }
        if (!empty($filters['types'])) {
            $ph = [];
            foreach ($filters['types'] as $i => $t) {
                $key = "type_$i"; $ph[] = ":$key"; $params[$key] = $t;
            }
            $conditions[] = 'type IN (' . implode(',', $ph) . ')';
        }
        if (!empty($filters['min_price'])) {
            $conditions[] = 'price_per_night >= :min_price';
            $params['min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $conditions[] = 'price_per_night <= :max_price';
            $params['max_price'] = $filters['max_price'];
        }
        if (!empty($filters['min_rating'])) {
            $conditions[] = 'rating_avg >= :min_rating AND rating_avg IS NOT NULL';
            $params['min_rating'] = $filters['min_rating'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(name ILIKE :q OR description ILIKE :q OR address ILIKE :q)';
            $params['q'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['envs'])) {
            $ph = [];
            foreach ($filters['envs'] as $i => $e) {
                $key = "env_$i";
                $ph[] = "environment_name ILIKE :$key";
                $params[$key] = $e;
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM camping_environments ce WHERE ce.camping_id = campings.id AND (' . implode(' OR ', $ph) . '))';
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function findInBbox(float $south, float $west, float $north, float $east): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug, type, latitude AS lat, longitude AS lng,
                    price_per_night AS price, rating_avg AS rating,
                    (SELECT url FROM camping_media WHERE camping_id = campings.id ORDER BY created_at LIMIT 1) AS image_url
             FROM campings
             WHERE approval_status = 1
               AND latitude BETWEEN :south AND :north
               AND longitude BETWEEN :west AND :east
             LIMIT 500"
        );
        $stmt->execute(compact('south', 'west', 'north', 'east'));
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    u.username AS created_by_username,
                    COALESCE(
                      (SELECT json_agg(json_build_object('id', m.id, 'type', m.type, 'url', m.url) ORDER BY m.created_at)
                       FROM camping_media m WHERE m.camping_id = c.id),
                      '[]'::json
                    ) AS media,
                    COALESCE(
                      (SELECT json_agg(f.facility_name ORDER BY f.facility_name)
                       FROM camping_facilities f WHERE f.camping_id = c.id),
                      '[]'::json
                    ) AS facilities,
                    COALESCE(
                      (SELECT json_agg(e.environment_name ORDER BY e.environment_name)
                       FROM camping_environments e WHERE e.camping_id = c.id),
                      '[]'::json
                    ) AS environments
             FROM campings c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['media']        = json_decode($row['media'], true);
        $row['facilities']   = json_decode($row['facilities'], true);
        $row['environments'] = json_decode($row['environments'], true);
        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    u.username AS created_by_username,
                    COALESCE(
                      (SELECT json_agg(json_build_object('id', m.id, 'type', m.type, 'url', m.url) ORDER BY m.created_at)
                       FROM camping_media m WHERE m.camping_id = c.id),
                      '[]'::json
                    ) AS media
             FROM campings c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.slug = :slug"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['media'] = json_decode($row['media'], true);
        return $row;
    }

    public function findForAdmin(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildAdminConditions($filters);
        $w = $where ? 'WHERE ' . $where : '';
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.slug, c.type, c.region, c.address,
                    c.price_per_night, c.capacity, c.approval_status, c.admin_feedback,
                    c.created_at, u.id AS user_id, u.username, u.email, u.full_name, u.avatar_url,
                    ov.id AS verification_id, ov.last_name, ov.first_name,
                    ov.business_type, ov.company_name, ov.registration_number,
                    ov.address_street, ov.address_number, ov.address_city, ov.address_zip,
                    ov.id_document_path, ov.registration_document_path,
                    ov.contact_phone, ov.contact_email, ov.submitted_at
             FROM campings c
             JOIN users u ON u.id = c.created_by
             LEFT JOIN organizer_verifications ov ON ov.user_id = c.created_by
             $w ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countForAdmin(array $filters): int
    {
        [$where, $params] = $this->buildAdminConditions($filters);
        $w = $where ? 'WHERE ' . $where : '';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM campings c $w");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildAdminConditions(array $filters): array
    {
        $conditions = [];
        $params     = [];
        if (isset($filters['status']) && $filters['status'] !== '') {
            $conditions[]     = 'c.approval_status = :status';
            $params['status'] = (int)$filters['status'];
        }
        return [implode(' AND ', $conditions), $params];
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.slug, c.type, c.region, c.address,
                    c.approval_status, c.admin_feedback, c.created_at,
                    (SELECT url FROM camping_media WHERE camping_id = c.id ORDER BY created_at LIMIT 1) AS cover_url
             FROM campings c
             WHERE c.created_by = :user_id
             ORDER BY c.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findForExport(): array
    {
        return $this->pdo->query("
            SELECT c.id, c.name, c.slug, c.type, c.address, c.region,
                   c.latitude, c.longitude, c.price_per_night, c.capacity,
                   c.rating_avg, c.rating_count, c.approval_status,
                   c.created_at, u.username AS created_by
            FROM campings c
            LEFT JOIN users u ON u.id = c.created_by
            ORDER BY c.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, array $data): int
    {
        $name        = htmlspecialchars(trim($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = isset($data['description']) ? htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8') : null;
        $slug        = $this->makeUniqueSlug($name);

        $stmt = $this->pdo->prepare(
            "INSERT INTO campings (created_by, name, slug, description, type, address, region,
                latitude, longitude, price_per_night, capacity)
             VALUES (:created_by, :name, :slug, :description, :type, :address, :region,
                :latitude, :longitude, :price_per_night, :capacity)
             RETURNING id"
        );
        $stmt->execute([
            'created_by'      => $userId,
            'name'            => $name,
            'slug'            => $slug,
            'description'     => $description,
            'type'            => $data['type'] ?? 'tent',
            'address'         => $data['address'] ?? null,
            'region'          => $data['region']  ?? null,
            'latitude'        => $data['latitude'],
            'longitude'       => $data['longitude'],
            'price_per_night' => $data['price_per_night'] ?? null,
            'capacity'        => $data['capacity'] ?? null,
        ]);
        $id = (int) $stmt->fetchColumn();
        $this->syncEnvironments($id, $data['environments'] ?? []);
        $this->syncFacilities($id, $data['facilities'] ?? []);
        return $id;
    }

    public function createFromImport(int $createdBy, string $slug, array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO campings
                (created_by, name, slug, description, type, address, region,
                 latitude, longitude, price_per_night, capacity, approval_status)
            VALUES
                (:created_by, :name, :slug, :description, :type, :address, :region,
                 :lat, :lng, :price, :capacity, 1)
        ");
        $stmt->execute([
            'created_by'  => $createdBy,
            'name'        => $data['name'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'type'        => $data['type'] ?? 'tent',
            'address'     => $data['address'] ?? null,
            'region'      => $data['region'] ?? null,
            'lat'         => $data['latitude'],
            'lng'         => $data['longitude'],
            'price'       => $data['price_per_night'] ?? null,
            'capacity'    => $data['capacity'] ?? null,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name','description','type','address','region','latitude','longitude','price_per_night','capacity'];
        $sets    = [];
        $params  = ['id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]      = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if ($sets) {
            $this->pdo->prepare("UPDATE campings SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        }
        if (array_key_exists('environments', $data)) $this->syncEnvironments($id, $data['environments']);
        if (array_key_exists('facilities',   $data)) $this->syncFacilities($id, $data['facilities']);
        return true;
    }

    private function syncEnvironments(int $campingId, array $values): void
    {
        $this->pdo->prepare("DELETE FROM camping_environments WHERE camping_id = :id")->execute(['id' => $campingId]);
        $stmt = $this->pdo->prepare("INSERT INTO camping_environments (camping_id, environment_name) VALUES (:cid, :name) ON CONFLICT DO NOTHING");
        foreach (array_filter($values) as $v) $stmt->execute(['cid' => $campingId, 'name' => $v]);
    }

    private function syncFacilities(int $campingId, array $values): void
    {
        $this->pdo->prepare("DELETE FROM camping_facilities WHERE camping_id = :id")->execute(['id' => $campingId]);
        $stmt = $this->pdo->prepare("INSERT INTO camping_facilities (camping_id, facility_name) VALUES (:cid, :name) ON CONFLICT DO NOTHING");
        foreach (array_filter($values) as $v) $stmt->execute(['cid' => $campingId, 'name' => $v]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM campings WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function resubmit(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE campings SET approval_status = 0, admin_feedback = NULL WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function approve(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE campings SET approval_status = 1 WHERE id = :id RETURNING id");
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function reject(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE campings SET approval_status = -1 WHERE id = :id RETURNING id");
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function rejectWithFeedback(int $id, string $feedback): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE campings SET approval_status = 2, admin_feedback = :feedback WHERE id = :id RETURNING id"
        );
        $stmt->execute(['id' => $id, 'feedback' => $feedback]);
        return (bool) $stmt->fetchColumn();
    }

    public function getOwnerId(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT created_by FROM campings WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    public function makeUniqueSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = strtr($slug, [
            'ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t',
            'Ă'=>'a','Â'=>'a','Î'=>'i','Ș'=>'s','Ş'=>'s','Ț'=>'t','Ţ'=>'t',
        ]);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-') ?: 'camping';

        $base = $slug;
        $i = 1;
        while ($this->slugExists($slug)) { $slug = "$base-" . (++$i); }
        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM campings WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        return (bool) $stmt->fetchColumn();
    }
}
