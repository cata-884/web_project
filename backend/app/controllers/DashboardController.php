<?php
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        // TODO: cele mai recente colectii publice, cartonase
    }
}
