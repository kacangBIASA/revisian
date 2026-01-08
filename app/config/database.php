<?php
// app/config/database.php

return [
    'driver'  => getenv('DB_DRIVER') ?: 'mysql',
    'host'    => getenv('DB_HOST') ?: 'queuenoww.mysql.database.azure.com',
    'port'    => (int)(getenv('DB_PORT') ?: 3306),
    'name'    => getenv('DB_DATABASE') ?: 'queuenow',
    'user'    => getenv('DB_USERNAME') ?: 'queuenow',
    'pass'    => getenv('DB_PASSWORD') ?: 'Queuenow123',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',

    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,

        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/azure-mysql-ca.pem',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ],
];
