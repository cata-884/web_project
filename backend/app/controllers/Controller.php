<?php
abstract class Controller
{
    protected mixed $model = null;
    protected string $viewPath = '';

    public function __construct()
    {
        // Auto-incarca modelul corespunzator (PlantsController -> PlantsModel)
        $modelName = str_replace('Controller', 'Model', static::class);
        if (class_exists($modelName)) {
            $this->model = new $modelName();
        }

        // Calea catre folderul de view-uri (PlantsController -> views/plants/)
        $folder = strtolower(str_replace('Controller', '', static::class));
        $this->viewPath = ROOT . SEP . 'app' . SEP . 'views' . SEP . $folder . SEP;
    }

    protected function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = $this->viewPath . $view . '.php';
        if (!file_exists($viewFile)) {
            die("View negasit: $viewFile");
        }

        extract($data);

        if ($layout === null) {
            include $viewFile;
            return;
        }

        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        include ROOT . SEP . 'app' . SEP . 'views' . SEP . 'layouts' . SEP . $layout . '.php';
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit();
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }

    protected function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }
}
