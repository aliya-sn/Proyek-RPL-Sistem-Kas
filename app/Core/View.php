<?php

namespace App\Core;

use RuntimeException;

class View
{
    /**
     * Render view template with data and optional layout.
     *
     * @param string $view e.g. 'auth/login' or 'dashboard/index'
     * @param array $data
     * @param string|false $layout e.g. 'layouts/main' or false for no layout
     * @return string
     */
    public static function render(string $view, array $data = [], string|false $layout = 'layouts/main'): string
    {
        $viewFile = base_path("app/Views/{$view}.php");

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file not found: {$viewFile}");
        }

        // Extract variables for view
        extract($data, EXTR_SKIP);

        // Start output buffering for view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // If no layout is requested, return raw content
        if ($layout === false) {
            return $content;
        }

        $layoutFile = base_path("app/Views/{$layout}.php");
        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Layout file not found: {$layoutFile}");
        }

        // Buffer layout rendering with $content available
        ob_start();
        require $layoutFile;
        return ob_get_clean();
    }

    /**
     * Render partial view.
     */
    public static function partial(string $partial, array $data = []): void
    {
        $partialFile = base_path("app/Views/{$partial}.php");
        if (file_exists($partialFile)) {
            extract($data, EXTR_SKIP);
            require $partialFile;
        }
    }
}
