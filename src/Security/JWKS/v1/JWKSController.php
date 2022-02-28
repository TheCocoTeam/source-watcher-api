<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\JWKS\v1;

use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Coco\SourceWatcherApi\Security\JWKS\JWKSHelper;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class JWKSController extends Controller
{
    /**
     * @var Logger
     */
    private Logger $log;

    /**
     * JWKSController constructor.
     */
    public function __construct()
    {
        $logPath = join( '/', [__DIR__, '..', '..', '..', 'logs', time() . '.log'] );

        $this->log = new Logger( JWKSController::class );
        $this->log->pushHandler( new StreamHandler( $logPath, Logger::INFO ) );

        parent::__construct();
    }

    /**
     * Allows processing the request to the endpoint.
     * It will return the JSON Web Key Set if requested via GET, or an error for any other case.
     * @param string $requestMethod
     * @param array $extraOptions
     */
    public function processRequest( string $requestMethod, array $extraOptions ): void
    {
        if ( $requestMethod === 'GET' ) {
            header( ResponseCodes::OK );

            $jwksHelper = new JWKSHelper();

            echo json_encode( ['keys' => [$jwksHelper->getJWK()]] );
        } else {
            header( ResponseCodes::BAD_REQUEST );

            echo json_encode( ['message' => 'unsupported method'] );
        }
    }
}
