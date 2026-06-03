<?php
readonly class StatsService
{
    public function __construct(private StatsRepository $stats) {}

    public function getSummary(): array
    {
        return array_merge($this->stats->getCounts(), [
            'bookings_by_status' => $this->stats->getBookingsByStatus(),
            'total_revenue'      => $this->stats->getTotalRevenue(),
            'top_regions'        => $this->stats->getTopRegions(),
            'top_campings'       => $this->stats->getTopCampings(),
            'bookings_per_month' => $this->stats->getBookingsPerMonth(),
        ]);
    }

    public function getChartData(string $type): array
    {
        return match($type) {
            'bookings_per_month' => ['rows' => $this->stats->getBookingsPerMonthChart(), 'title' => 'Rezervari / luna (12 luni)'],
            'top_regions'        => ['rows' => $this->stats->getTopRegionsChart(),       'title' => 'Campinguri pe regiune'],
            'revenue_per_month'  => ['rows' => $this->stats->getRevenuePerMonthChart(),  'title' => 'Venituri confirmate / luna (RON)'],
            default              => throw new ValidationException('type invalid'),
        };
    }

    public function getReportData(): array
    {
        return array_merge($this->stats->getCounts(), [
            'total_rev'   => $this->stats->getTotalRevenue(),
            'top_campings'=> $this->stats->getTopCampings(),
        ]);
    }
}
