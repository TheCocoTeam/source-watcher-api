<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\Refresh\v1;

use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class RefreshController extends Controller
{
    use ApiResponse;

    /**
     * @var Logger
     */
    private Logger $log;

    /**
     * DatabaseSeedingController constructor.
     */
    public function __construct()
    {
        $logPath = join('/', [__DIR__, '..', '..', '..', 'logs', time() . '.log']);

        $this->log = new Logger(RefreshController::class);
        $this->log->pushHandler(new StreamHandler($logPath, Logger::INFO));

        parent::__construct();
    }

    /**
     * Allows processing the request to the endpoint.
     * @param string $requestMethod
     * @param array $extraOptions
     */
    public function processRequest(string $requestMethod, array $extraOptions): void
    {
        if ($requestMethod == 'POST') {
            $response = $this->refreshToken();
        } else {
            $response = $this->notFoundResponse();
        }

        header($response['status_code_header']);

        if ($response['body']) {
            echo $response['body'];
        }
    }

    private function refreshToken()
    {

    }
}
