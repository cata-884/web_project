<?php
class CampingsModel extends Model
{
    protected string $table = 'campings';

    /**
     * Listare cu filtre: region, type, min_price, max_price, search, limit, offset.
     */
    public function search(array $filters = []): array
    {
        $conditions = ['is_published = TRUE'];
        $params     = [];

        if (!empty($filters['region'])) {
            $conditions[] = 'region = :region';
            $params['region'] = $filters['region'];
        }
        if (!empty($filters['type'])) {
            $conditions[] = 'type = :type';
            $params['type'] = $filters['type'];
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
            $conditions[] = 'rating_avg >= :min_rating';
            $params['min_rating'] = $filters['min_rating'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(name ILIKE :q OR description ILIKE :q OR address ILIKE :q)';
            $params['q'] = '%' . $filters['search'] . '%';
        }

        $where  = implode(' AND ', $conditions);
        $limit  = min(100, max(1, (int)($filters['limit']  ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT id, name, slug, type, region, latitude, longitude,
                       price_per_night, capacity, rating_avg, rating_count, created_at
                FROM campings
                WHERE $where
                ORDER BY rating_avg DESC NULLS LAST, created_at DESC
                LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countSearch(array $filters = []): int
    {
        $conditions = ['is_published = TRUE'];
        $params     = [];

        if (!empty($filters['region'])) {
            $conditions[] = 'region = :region';
            $params['region'] = $filters['region'];
        }
        if (!empty($filters['type'])) {
            $conditions[] = 'type = :type';
            $params['type'] = $filters['type'];
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
            $conditions[] = 'rating_avg >= :min_rating';
            $params['min_rating'] = $filters['min_rating'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(name ILIKE :q OR description ILIKE :q OR address ILIKE :q)';
            $params['q'] = '%' . $filters['search'] . '%';
        }

        $where = implode(' AND ', $conditions);
        $sql   = "SELECT COUNT(id) FROM campings WHERE $where";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Marker-e pentru harta, filtrate pe bounding box.
     */
    public function findInBbox(float $south, float $west, float $north, float $east): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug, type, latitude AS lat, longitude AS lng,
                    price_per_night AS price, rating_avg AS rating,
                    (SELECT url FROM camping_media WHERE camping_id = campings.id ORDER BY sort_order LIMIT 1) AS image_url
             FROM campings
             WHERE is_published = TRUE
               AND latitude  BETWEEN :south AND :north
               AND longitude BETWEEN :west  AND :east
             LIMIT 500"
        );
        $stmt->execute(compact('south', 'west', 'north', 'east'));
        return $stmt->fetchAll();
    }

    /**
     * Detaliu camping cu media agregate in JSON
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    u.username AS created_by_username,
                    COALESCE(
                      (SELECT json_agg(json_build_object(
                                  'id', m.id, 'type', m.type, 'url', m.url, 'sort_order', m.sort_order
                              ) ORDER BY m.sort_order)
                       FROM camping_media m WHERE m.camping_id = c.id),
                      '[]'::json
                    ) AS media
             FROM campings c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        // media vine ca string JSON din PG - il decodam pentru output curat
        $row['media'] = json_decode($row['media'], true);
        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    u.username AS created_by_username,
                    COALESCE(
                      (SELECT json_agg(json_build_object(
                                  'id', m.id, 'type', m.type, 'url', m.url, 'sort_order', m.sort_order
                              ) ORDER BY m.sort_order)
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

    public function create(int $userId, array $data): int
    {
        $name        = htmlspecialchars(trim($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = isset($data['description']) ? htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8') : null;
        $slug        = $this->makeUniqueSlug($name);

        $stmt = $this->pdo->prepare(
            "INSERT INTO campings (
                created_by, name, slug, description, type, address, region,
                latitude, longitude, price_per_night, capacity, is_published
             ) VALUES (
                :created_by, :name, :slug, :description, :type, :address, :region,
                :latitude, :longitude, :price_per_night, :capacity, :is_published
             ) RETURNING id"
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
            'is_published'    => $data['is_published'] ?? true,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name','description','type','address','region',
            'latitude','longitude','price_per_night','capacity','is_published'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (!$sets) return false;

        $sql = "UPDATE campings SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE campings SET is_published = FALSE WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Returneaza created_by pentru un camping
     */
    public function getOwnerId(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT created_by FROM campings WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    private function makeUniqueSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = strtr($slug, [
            'a'=>'a','a'=>'a','i'=>'i','s'=>'s','t'=>'t',
            'A'=>'a','A'=>'a','I'=>'i','S'=>'s','T'=>'t',
        ]);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'camping';

        $base = $slug;
        $i = 1;
        while ($this->slugExists($slug)) {
            $i++;
            $slug = "$base-$i";
        }
        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM campings WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        return (bool) $stmt->fetchColumn();
    }
}