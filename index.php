<?php

error_reporting(E_ERROR | E_PARSE);

require_once(__DIR__ . "/vendor/autoload.php");

use Dotenv\Dotenv;
use Coco\SourceWatcherApi\Database\DatabaseMigrator;
use Coco\SourceWatcherApi\Framework\ResponseCodes;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

$uri = explode("/", $uri);

$allowedControllers = [
    "api" => [
        "v1" => [
            ".well-known" => [
                "jwks.json" => "Coco\\SourceWatcherApi\\Security\\JWKS\\v1\\JWKSController"
            ],
            "credentials" => "Coco\\SourceWatcherApi\\Security\\v1\\CredentialsController",
            "database-seeding" => "Coco\\SourceWatcherApi\\Database\\v1\\DatabaseSeedingController",
            "item" => [
                // api/v1/item or api/v1/item/
                "" => "Coco\\SourceWatcherApi\\Core\\Item\\ItemController",

                // api/v1/item/123
                "/" . "^[0-9]+" . "/" => "Coco\\SourceWatcherApi\\Core\\Item\\ItemController"
            ],
            "jwt" => "Coco\\SourceWatcherApi\\Security\\JWT\\v1\\JWTController"
        ]
    ]
];

/**
 * @param $index
 * @param $uri
 * @param $allowedControllers
 * @return array
 */
function getEndpointValue($index, $uri, $allowedControllers): array
{
    $key = $uri[$index];
    $value = null;

    if (array_key_exists($key, $allowedControllers) && isset($allowedControllers[$key])) {
        $value = $allowedControllers[$key];
    }

    $extraValue = null;

    if (empty($value)) {
        foreach ($allowedControllers as $currentKey => $currentValue) {
            if (empty($currentKey)) {
                continue;
            }

            $match = preg_match($currentKey, $key);

            if ($match) {
                $value = $currentValue;
                $extraValue = $key;
                break;
            }
        }
    }

    if (empty($value) || $index == sizeof($uri) - 1) {
        return is_array($value) ? (array_key_exists("", $value) && isset($value[""]) ? [$value[""], null] : [null, null]) : [$value, $extraValue];
    }

    return getEndpointValue($index + 1, $uri, $value);
}

try {
    Dotenv::createImmutable(__DIR__)->load();

    $dbHost = $_ENV["DB_HOST"];
    $dbName = $_ENV["DB_NAME"];
    $dbUser = $_ENV["DB_USER"];
    $dbPass = $_ENV["DB_PASS"];

    $connection = new PDO(sprintf("mysql:host=%s;dbname=%s", $dbHost, $dbName), $dbUser, $dbPass);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $databaseMigrator = new DatabaseMigrator();
    $databaseMigrator->migrateDatabase($dbName);
} catch (PDOException $e) {
    header(ResponseCodes::NOT_FOUND);
    exit();
}

$requestedEndpoint = getEndpointValue(1, $uri, $allowedControllers);

$className = $requestedEndpoint[0];

if (empty($className)) {
    header(ResponseCodes::NOT_FOUND);
    exit();
}

$object = new $className();
$object->setRequestData($_REQUEST);
$object->processRequest($_SERVER["REQUEST_METHOD"], [$requestedEndpoint[1]]);
