<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\JWT\v1;

use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Coco\SourceWatcherApi\Security\JWT\JWTHelper;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * This endpoint verifies if an access token is valid or not.
 */
class JWTController extends Controller
{
    use ApiResponse;

    private Logger $log;

    public function __construct()
    {
        $logPath = join('/', [__DIR__, '..', '..', '..', '..', 'logs', time() . '.log']);

        $this->log = new Logger(JWTController::class);
        $this->log->pushHandler(new StreamHandler($logPath, Logger::INFO));

        parent::__construct();
    }

    public function processRequest(string $requestMethod, array $extraOptions): void
    {
        if ($requestMethod == 'POST') {
            $response = $this->validateJWT();
        } else {
            $response = $this->notFoundResponse();
        }

        header($response['status_code_header']);

        if ($response['body']) {
            echo $response['body'];
        }
    }

    private function validateJWT(): array
    {
        $jwt = $_SERVER['HTTP_X_ACCESS_TOKEN'];

        if (empty($jwt)) {
            return $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Missing JWT');
        }

        $jwtHelper = new JWTHelper();
        $jwtIsValid = $jwtHelper->jwtIsValid($jwt);

        if (!$jwtIsValid) {
            $this->log->info(sprintf('An invalid JWT was provided: %s', $jwt));

            return $this->makeResponse(ResponseCodes::UNAUTHORIZED, 'Invalid JWT');
        }

        return $this->makeResponse(ResponseCodes::OK, 'JWT is valid');
    }
}
