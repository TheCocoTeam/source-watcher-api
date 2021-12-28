<?php

namespace Coco\SourceWatcherApi\Security\v1;

use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Firebase\JWT\JWT;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class CredentialsController
 * @package Coco\SourceWatcherApi\Security\v1
 */
class CredentialsController extends Controller
{
    use ApiResponse;

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

        $this->log = new Logger( CredentialsController::class );
        $this->log->pushHandler( new StreamHandler( $logPath, Logger::INFO ) );

        parent::__construct();
    }

    /**
     * Allows processing the request to the endpoint.
     * @param string $requestMethod
     * @param array $extraOptions
     */
    public function processRequest( string $requestMethod, array $extraOptions ): void
    {
        $response = null;

        if ( $requestMethod == 'POST' ) {
            $response = $this->validateCredentials();
        }
        else {
            $response = $this->notFoundResponse();
        }

        header( $response['status_code_header'] );

        if ( $response['body'] ) {
            echo $response['body'];
        }
    }

    /**
     * @return array
     */
    private function validateCredentials(): array {
        $username = $this->requestData['username'];
        $password = $this->requestData['password'];

        $user = null;

        if ( empty( $username) ) {
            return $this->makeResponse( ResponseCodes::BAD_REQUEST, 'Missing username' );
        }

        if ( empty( $password) ) {
            return $this->makeResponse( ResponseCodes::BAD_REQUEST, 'Missing password' );
        }

        try {
            $userDao = new UserDAO();
            $user = $userDao->getUser( $username );

            if ( empty( $user->getId() ) ) {
                return $this->makeResponse( ResponseCodes::NOT_FOUND );
            }
        }
        catch ( FrameworkException $exception ) {
            return $this->makeResponse( ResponseCodes::INTERNAL_SERVER_ERROR, $exception->getMessage() );
        }

        if ( !password_verify( $password, $user->getPassword() ) ) {
            return $this->makeResponse( ResponseCodes::UNAUTHORIZED );
        }

        $issuedAt = date_timestamp_get( date_create() );
        $expiresAt = $issuedAt + 3600;

        $payload = [
            'data' => ['userId' => $user->getId()],
            "iss" => $_SERVER['HTTP_HOST'],
            "aud" => $_SERVER['HTTP_HOST'],
            "iat" => $issuedAt,
            "eat" => $expiresAt
        ];

        $privateKey = file_get_contents( join( '/', [__DIR__, 'keys', 'current', 'private.pem'] ) );

        $accessToken = JWT::encode( $payload, $privateKey, 'RS256' );

        return $this->makeResponse( ResponseCodes::OK, $accessToken );
    }
}
