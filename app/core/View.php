<?php
// app/core/View.php

class View
{
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $viewPath = __DIR__ . '/../../resources/views/' . $view . '.php';
        if (!is_file($viewPath)) {
            http_response_code(500);
            return "View not found: " . htmlspecialchars($viewPath);
        }

        // Inject data ke scope view
        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout) {
            $layoutPath = __DIR__ . '/../../resources/views/' . $layout . '.php';
            if (!is_file($layoutPath)) {
                http_response_code(500);
                return "Layout not found: " . htmlspecialchars($layoutPath);
            }

            ob_start();
            require $layoutPath;   // layout akan pakai $content
            return ob_get_clean();
        }

        return $content;
    }
}
