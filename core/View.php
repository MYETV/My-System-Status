<?php
// path: core/View.php

namespace App\Core;

class View
{
    /**
     * Render a view inside a parent layout (default 'layouts/admin').
     */
    public static function render(string $viewPath, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        extract($data);

        $baseDir = dirname(__DIR__) . '/app/Views/';
        $viewFile = $baseDir . ltrim($viewPath, '/') . '.php';

        if (!file_exists($viewFile)) {
            throw new \Exception("View [{$viewPath}] not found at {$viewFile}");
        }

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Render inside layout if specified
        if ($layout) {
            $layoutFile = $baseDir . ltrim($layout, '/') . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }
}