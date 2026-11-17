<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Session;

abstract class BaseController
{
    /**
     * Render a view within layout.
     */
    protected function render(string $view, array $data = [], string|false $layout = 'layouts/main'): void
    {
        // Inject global view data
        $globalData = [
            'authUser' => Session::get('user'),
            'rolePrefix' => $this->getRolePrefix(),
            'appName' => config('app.name'),
            'flashSuccess' => Session::getFlash('success'),
            'flashError' => Session::getFlash('error'),
            'flashWarning' => Session::getFlash('warning'),
            'flashInfo' => Session::getFlash('info'),
        ];

        $mergedData = array_merge($globalData, $data);
        echo View::render($view, $mergedData, $layout);
    }

    /**
     * Send JSON response.
     */
    protected function json(mixed $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    /**
     * Redirect to another URL with optional flash message.
     */
    protected function redirect(string $path, array $flash = []): void
    {
        foreach ($flash as $type => $message) {
            Session::setFlash($type, $message);
        }
        Response::redirect($path);
    }

    /**
     * Validate simple array rules.
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = trim((string)($data[$field] ?? ''));

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && empty($value)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi.';
                    break;
                }

                if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Format email tidak valid.';
                    break;
                }

                if (str_starts_with($rule, 'min:') && !empty($value)) {
                    $min = (int) substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " minimal harus {$min} karakter.";
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get URL prefix based on the current user's role.
     * Returns 'admin' for super_user, 'bendahara' for bendahara.
     */
    protected function getRolePrefix(): string
    {
        $role = Session::get('user')['role'] ?? '';
        return match ($role) {
            'bendahara' => 'bendahara',
            default => 'admin',
        };
    }
}
