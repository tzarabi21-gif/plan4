<?php
declare(strict_types=1);
return [
 'app_name' => getenv('APP_NAME') ?: 'NEXA',
 'app_url' => rtrim(getenv('APP_URL') ?: 'http://localhost/NEXA/public', '/'),
 'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
 'db' => [
   'host' => getenv('DB_HOST') ?: '127.0.0.1',
   'name' => getenv('DB_NAME') ?: 'nexa',
   'user' => getenv('DB_USER') ?: 'root',
   'pass' => getenv('DB_PASS') ?: '',
   'charset' => 'utf8mb4',
 ],
 'timezone' => 'Asia/Tehran',
];
