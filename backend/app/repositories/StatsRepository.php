<?php
class StatsRepository extends Repository
{
    public function getCounts(): array
    {
        return [
            'nr_users'    => (int) $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'nr_campings' => (int) $this->pdo->query("SELECT COUNT(*) FROM campings WHERE approval_status = 1")->fetchColumn(),
            'nr_pending'  => (int) $this->pdo->query("SELECT COUNT(*) FROM campings WHERE approval_status = 0")->fetchColumn(),
            'nr_bookings' => (int) $this->pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        ];
    }

    public function getBookingsByStatus(): array
    {
        return $this->pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getTotalRevenue(): float
    {
        $tp = BookingsRepository::totalPriceExpr();
        return (float) $this->pdo->query(
            "SELECT COALESCE(SUM($tp), 0) FROM bookings b JOIN campings c ON c.id = b.camping_id WHERE b.status = 'confirmed'"
        )->fetchColumn();
    }

    public function getTopRegions(int $limit = 5): array
    {
        return $this->pdo->query(
            "SELECT region, COUNT(*) AS cnt FROM campings WHERE approval_status = 1 AND region IS NOT NULL
             GROUP BY region ORDER BY cnt DESC LIMIT $limit"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopCampings(int $limit = 5): array
    {
        return $this->pdo->query(
            "SELECT c.id, c.name, ROUND(AVG(r.rating)::numeric,2) AS avg_rating, COUNT(r.id) AS nr_reviews
             FROM campings c JOIN reviews r ON r.camping_id = c.id
             WHERE c.approval_status = 1
             GROUP BY c.id, c.name HAVING COUNT(r.id) >= 1
             ORDER BY avg_rating DESC, nr_reviews DESC LIMIT $limit"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingsPerMonth(): array
    {
        return $this->pdo->query(
            "SELECT TO_CHAR(created_at,'YYYY-MM') AS month, COUNT(*) AS cnt
             FROM bookings WHERE created_at >= NOW() - INTERVAL '12 months'
             GROUP BY month ORDER BY month"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingsPerMonthChart(): array
    {
        return $this->pdo->query(
            "SELECT TO_CHAR(created_at,'Mon YY') AS label, COUNT(*) AS value
             FROM bookings WHERE created_at >= NOW() - INTERVAL '12 months'
             GROUP BY TO_CHAR(created_at,'YYYY-MM'), TO_CHAR(created_at,'Mon YY')
             ORDER BY MIN(created_at)"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRevenuePerMonthChart(): array
    {
        $tp = BookingsRepository::totalPriceExpr();
        return $this->pdo->query(
            "SELECT TO_CHAR(b.created_at,'Mon YY') AS label,
                    COALESCE(SUM($tp), 0) AS value
             FROM bookings b JOIN campings c ON c.id = b.camping_id
             WHERE b.status='confirmed' AND b.created_at >= NOW() - INTERVAL '12 months'
             GROUP BY TO_CHAR(b.created_at,'YYYY-MM'), TO_CHAR(b.created_at,'Mon YY')
             ORDER BY MIN(b.created_at)"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopRegionsChart(int $limit = 8): array
    {
        return $this->pdo->query(
            "SELECT COALESCE(region,'N/A') AS label, COUNT(*) AS value
             FROM campings WHERE approval_status = 1
             GROUP BY region ORDER BY value DESC LIMIT $limit"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
