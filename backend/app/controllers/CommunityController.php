<?php
class CommunityController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        // TODO: lista prieteni + cereri pending
    }

    public function apiSearch(): void
    {
        $this->requireAuth();
        // TODO: cauta utilizatori dupa username/email
        $this->json(['users' => []]);
    }

    public function addFriend(int $userId): void
    {
        $this->requireAuth();
        // TODO: trimite cerere de prietenie (status=pending)
    }

    public function acceptFriend(int $userId): void
    {
        $this->requireAuth();
        // TODO: status=accepted
    }

    public function removeFriend(int $userId): void
    {
        $this->requireAuth();
        // TODO
    }
}
