<?php
declare(strict_types=1);

if (!function_exists('app_config')) {
    function app_config(?string $key = null, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            date_default_timezone_set('America/Lima');

            $baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: '/Asistencia_FIEI'), '/');

            $config = [
                'app_name' => 'Asistencia FIEI',
                'base_url' => $baseUrl,
                'db' => [
                    'host' => getenv('DB_HOST') ?: '127.0.0.1',
                    'port' => getenv('DB_PORT') ?: '3306',
                    'database' => getenv('DB_NAME') ?: 'asistencia_fiei',
                    'username' => getenv('DB_USER') ?: 'root',
                    'password' => getenv('DB_PASS') ?: '',
                ],
                'security' => [
                    'session_name' => 'fiei_session',
                    'cookie_lifetime' => 60 * 60 * 8,
                ],
            ];
        }

        if ($key === null) {
            return $config;
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
