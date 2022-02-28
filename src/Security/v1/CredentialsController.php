<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\v1;

use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Coco\SourceWatcherApi\Security\JWKS\JWKSHelper;
use Coco\SourceWatcherApi\Security\Refresh\RefreshTokenDAO;
use Coco\SourceWatcherApi\Security\User\UserDAO;
use Firebase\JWT\JWT;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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
        $logPath = join('/', [__DIR__, '..', '..', '..', 'logs', time() . '.log']);

        $this->log = new Logger(CredentialsController::class);
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
        $response = null;

        if ($requestMethod == 'POST') {
            $response = $this->validateCredentials();
        } else {
            $response = $this->notFoundResponse();
        }

        header($response['status_code_header']);

        if ($response['body']) {
            echo $response['body'];
        }
    }

    /**
     * @return array
     */
    private function validateCredentials(): array
    {
        $username = $this->requestData['username'];
        $password = $this->requestData['password'];

        if (empty($username)) {
            return $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Missing username');
        }

        if (empty($password)) {
            return $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Missing password');
        }

        try {
            $userDao = new UserDAO();
            $user = $userDao->getUser($username);

            if (empty($user->getId())) {
                return $this->makeResponse(ResponseCodes::NOT_FOUND, 'User not found');
            }
        } catch (FrameworkException $exception) {
            return $this->makeResponse(ResponseCodes::INTERNAL_SERVER_ERROR, $exception->getMessage());
        }

        if (!password_verify($password, $user->getPassword())) {
            return $this->makeResponse(ResponseCodes::UNAUTHORIZED, 'Wrong credentials');
        }

        $payload = [
            'data' => ['userId' => $user->getId()],
            "iss" => $_SERVER['HTTP_HOST'],
            "aud" => $_SERVER['HTTP_HOST'],
            "iat" => strtotime( 'now' ),
            "eat" => strtotime( '+1 hour' )
        ];

        $jwksHelper = new JWKSHelper();

        $privateKey = $jwksHelper->getPrivateKey();
        $alg = 'RS256';
        $key = $jwksHelper->getJWK();

        $accessToken = JWT::encode($payload, $privateKey, $alg, $key['kid']);

        $refreshToken = $jwksHelper->getRefreshToken();

        try {
            $refreshTokenDao = new RefreshTokenDAO();
            $refreshTokenDao->insertRefreshToken($user->getId(), $refreshToken);
        } catch (FrameworkException $exception) {
            return $this->makeResponse(ResponseCodes::INTERNAL_SERVER_ERROR, $exception->getMessage());
        }

        $response = ['accessToken' => $accessToken, 'refreshToken' => $refreshToken];

        return $this->makeArrayResponse(ResponseCodes::OK, $response);
    }
}
