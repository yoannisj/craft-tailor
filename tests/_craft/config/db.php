<?php

$dbService = getenv('DB_SERVICE');
switch ($dbService)
{
    case 'postgres':
        $dbDriver = 'pgsql';
        $dbport = 5432;
        break;
    default:
        $dbDriver = 'mysql';
        $dbPort = 3306;
}

return [
    'driver' => $dbDriver,
    'server' => $dbService,
    'port' => $dbPort,
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'database' => getenv('DB_NAME'),
    'schema' => getenv('DB_SCHEMA'),
    'tablePrefix' => getenv('DB_TABLE_PREFIX'),
];