<?php

error_reporting(E_ERROR | E_PARSE);

require_once(__DIR__ . "/vendor/autoload.php");

use Coco\SourceWatcherApi\Core\Item\ItemController;
use Coco\SourceWatcherApi\Database\DatabaseMigrator;
use Coco\SourceWatcherApi\Database\v1\DatabaseSeedingController;
use Coco\SourceWatcherApi\Database\v1\DbConnectionTypeController;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Coco\SourceWatcherApi\Security\Credentials\v1\CredentialsController;
use Coco\SourceWatcherApi\Security\JWKS\v1\JWKSController;
use Coco\SourceWatcherApi\Security\JWT\JWTHelper;
use Coco\SourceWatcherApi\Security\JWT\v1\JWTController;
use Coco\SourceWatcherApi\Security\Refresh\v1\RefreshController;
use Dotenv\Dotenv;

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
                "jwks.json" => JWKSController::class
            ],
            "credentials" => CredentialsController::class,
            "database-seeding" => DatabaseSeedingController::class,
            "db-connection-type" => DbConnectionTypeController::class,
            "item" => [
                // api/v1/item or api/v1/item/
                "" => ItemController::class,

                // api/v1/item/123
                "/" . "^[0-9]+" . "/" => ItemController::class
            ],
            "jwt" => JWTController::class,
            "refresh-token" => RefreshController::class,
        ]
    ]
];

$authenticationSettings = [
    CredentialsController::class => false,
    DatabaseSeedingController::class => true,
    DbConnectionTypeController::class => true,
    ItemController::class => true,
    JWKSController::class => false,
    JWTController::class => false,
    RefreshController::class => false,
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

// Run database migrations

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

if ($authenticationSettings[$className] == true) {
    $accessToken = $_REQUEST['access_token'];
    $refreshToken = $_REQUEST['refresh_token'];

    if (empty($accessToken)) {
        $response = $object->makeResponse(ResponseCodes::BAD_REQUEST, 'Missing JWT');
        header($response['status_code_header']);

        if ($response['body']) {
            echo $response['body'];
        }

        exit();
    } else {
        $jwtHelper = new JWTHelper();
        $jwtIsValid = $jwtHelper->jwtIsValid($accessToken);

        if (!$jwtIsValid) {
            if (empty($refreshToken)) {
                // No refresh token given, only access token

                $response = $object->makeResponse(ResponseCodes::UNAUTHORIZED, 'Missing refresh token');
                header($response['status_code_header']);

                if ($response['body']) {
                    echo $response['body'];
                }

                exit();
            } else {
                // Attempt to get a new access token and refresh token


            }
        }
    }
}

$object->setRequestData($_REQUEST);
$object->processRequest($_SERVER["REQUEST_METHOD"], [$requestedEndpoint[1]]);
