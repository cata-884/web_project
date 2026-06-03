<?php
readonly class ExportService
{
    public function __construct(
        private CampingsRepository $campings,
        private BookingsRepository $bookings,
        private ReviewsRepository  $reviews,
        private UserRepository     $users,
    ) {}

    public function getCampings(): array { return $this->campings->findForExport(); }
    public function getBookings(): array { return $this->bookings->findForExport(); }
    public function getReviews(): array  { return $this->reviews->findForExport(); }
    public function getUsers(): array    { return $this->users->findForExport(); }
}
