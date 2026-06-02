<?php
class ContactController extends Controller
{
    private ContactService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ContactService(new ContactRepository());
    }

    public function store(): void
    {
        $this->service->store($this->getJsonBody());
        $this->json(['ok' => true, 'message' => 'Mesaj trimis cu succes']);
    }

    public function index(): void
    {
        $this->requireAdmin();
        $this->json(['messages' => array_map([ContactDTO::class, 'fromRow'], $this->service->list())]);
    }
}
