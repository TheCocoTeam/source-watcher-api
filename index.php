<?php

error_reporting(E_ERROR | E_PARSE);

require_once(__DIR__ . "/vendor/autoload.php");

// Fallback for Coco\SourceWatcher (Core) when vendor symlink is broken (e.g. Docker without Core mount)
$coreSrc = null;
if (is_dir(__DIR__ . '/source-watcher-core/src')) {
    $coreSrc = __DIR__ . '/source-watcher-core/src';
} elseif (is_dir(__DIR__ . '/../source-watcher-core/src')) {
    $coreSrc = __DIR__ . '/../source-watcher-core/src';
}
if ($coreSrc !== null) {
    spl_autoload_register(function ($class) use ($coreSrc) {
        if (strpos($class, 'Coco\\SourceWatcher\\') !== 0) {
            return;
        }
        $relative = str_replace('Coco\\SourceWatcher\\', '', $class);
        $relativePath = str_replace('\\', '/', $relative) . '.php';
        $file = $coreSrc . '/' . $relativePath;
        if (is_file($file)) {
            require_once $file;
            return;
        }
        // StepLoader uses textToPascalCase which lowercases segments (e.g. ConvertCase -> Convertcase); try case-insensitive match
        $dir = $coreSrc . '/' . dirname($relativePath);
        $want = basename($relativePath);
        if (is_dir($dir)) {
            foreach (scandir($dir) as $entry) {
                if ($entry !== '.' && $entry !== '..' && strcasecmp($entry, $want) === 0) {
                    require_once $dir . '/' . $entry;
                    return;
                }
            }
        }
    }, true, true);
}

use Coco\SourceWatcherApi\Core\Item\ItemController;
use Coco\SourceWatcherApi\Database\DatabaseMigrator;
use Coco\SourceWatcherApi\Pipeline\v1\RunTransformationController;
use Coco\SourceWatcherApi\Pipeline\v1\StepsController;
use Coco\SourceWatcherApi\Pipeline\v1\TransformationController;
use Coco\SourceWatcherApi\Database\v1\DatabaseSeedingController;
use Coco\SourceWatcherApi\Database\v1\DbConnectionTypeController;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Coco\SourceWatcherApi\Security\Credentials\v1\CredentialsController;
use Coco\SourceWatcherApi\Security\JWKS\v1\JWKSController;
use Coco\SourceWatcherApi\Security\JWT\JWTHelper;
use Coco\SourceWatcherApi\Security\JWT\v1\JWTController;
use Coco\SourceWatcherApi\Security\Logout\v1\LogoutController;
use Coco\SourceWatcherApi\Security\Refresh\v1\RefreshController;
use Dotenv\Dotenv;

// CORS: allow board (e.g. http://localhost:8080) to call API with credentials (cookies).
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:8080', 'http://127.0.0.1:8080'];
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, x-access-token, x-refresh-token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

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
            "logout" => LogoutController::class,
            "refresh-token" => RefreshController::class,
            "steps" => StepsController::class,
            "transformation" => TransformationController::class,
            "transformation-run" => RunTransformationController::class,
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
    LogoutController::class => false,
    RefreshController::class => false,
    StepsController::class => false,
    TransformationController::class => false,
    RunTransformationController::class => true,
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
    $envPath = __DIR__;
    if (is_file($envPath . '/.env')) {
        Dotenv::createImmutable($envPath)->load();
    } else {
        // No .env (e.g. Docker with env from compose): use getenv() so $_ENV is populated
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'DB_CHARSET', 'DB_DRIVER', 'DB_ADAPTER'] as $key) {
            $val = getenv($key);
            if ($val !== false) {
                $_ENV[$key] = $val;
            }
        }
    }

    $dbHost = $_ENV["DB_HOST"];
    $dbName = $_ENV["DB_NAME"];
    $dbUser = $_ENV["DB_USER"];
    $dbPass = $_ENV["DB_PASS"];

    $connection = new PDO(sprintf("mysql:host=%s;dbname=%s", $dbHost, $dbName), $dbUser, $dbPass);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $databaseMigrator = new DatabaseMigrator();
    try {
        $databaseMigrator->migrateDatabase($dbName);
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $prev = $e->getPrevious();
        if ($prev) {
            $msg .= $prev->getMessage();
        }
        // Phinx may throw when phinxlog table already exists (e.g. concurrent or repeated runs)
        if (strpos($msg, 'phinxlog') !== false && strpos($msg, 'already exists') !== false) {
            // Schema table exists; continue with the request
        } else {
            throw $e;
        }
    }

    // Ensure default user exists (e.g. fresh Docker DB): seed if users table is empty
    try {
        $userCount = $connection->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ((int) $userCount === 0) {
            $databaseMigrator->seedDatabase($dbName, 'UserSeeder');
        }
    } catch (PDOException $e) {
        // Table may not exist yet; ignore
    }
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
    $accessToken = $_REQUEST['access_token'] ?? $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '';
    $refreshToken = $_REQUEST['refresh_token'] ?? $_SERVER['HTTP_X_REFRESH_TOKEN'] ?? '';

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

// Merge JSON body into request data so POST with Content-Type: application/json is available to controllers
$requestData = $_REQUEST;
$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)
    && (strpos($contentType, 'application/json') !== false)) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $requestData = array_merge($requestData, $decoded);
        }
    }
}
$object->setRequestData($requestData);
$object->processRequest($_SERVER["REQUEST_METHOD"], [$requestedEndpoint[1]]);
