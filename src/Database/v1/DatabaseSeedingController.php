<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Database\v1;

use Coco\SourceWatcherApi\Database\DatabaseMigrator;
use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\ResponseCodes;
use Coco\SourceWatcherApi\Security\JWKS\JWKSHelper;
use DbConnectionSeeder;
use DbConnectionTypeSeeder;
use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class DatabaseSeedingController
 * @package Coco\SourceWatcherApi\Database\v1
 */
class DatabaseSeedingController extends Controller
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
        $logPath = join( '/', [__DIR__, '..', '..', '..', 'logs', time() . '.log'] );

        $this->log = new Logger( DatabaseSeedingController::class );
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
            $response = $this->seedTable();
        }
        else {
            $response = $this->notFoundResponse();
        }

        header( $response['status_code_header'] );

        if ( $response['body'] ) {
            echo $response['body'];
        }
    }

    public function getName( string $className ): string {
        $path = explode( '\\', $className );
        return array_pop( $path );
    }

    /**
     * @return array
     */
    private function seedTable(): array {
        $jwt = $this->requestData['jwt'];
        $table = $this->requestData['table'];

        if ( empty( $jwt) ) {
            return $this->makeResponse( ResponseCodes::BAD_REQUEST, 'Missing JWT' );
        }

        if ( empty( $table) ) {
            return $this->makeResponse( ResponseCodes::BAD_REQUEST, 'Missing table' );
        }

        $jwksHelper = new JWKSHelper();
        $jwtIsValid = $jwksHelper->jwtIsValid( $jwt );

        if ( !$jwtIsValid ) {
            $this->log->info(
                sprintf( 'An invalid JWT was provided: %s', $jwt )
            );

            return $this->makeResponse( ResponseCodes::UNAUTHORIZED, 'Invalid JWT' );
        }

        $seed = null;

        switch ( $table ) {
            case 'db_connection':
                $seed = self::getName( DbConnectionSeeder::class );
                break;
            case 'db_connection_type':
                $seed = self::getName( DbConnectionTypeSeeder::class );
                break;
            case 'user':
                $seed = self::getName( UserSeeder::class );
                break;
        }

        if ( empty( $seed ) ) {
            return $this->makeResponse( ResponseCodes::BAD_REQUEST, 'Wrong seed' );
        }

        Dotenv::createImmutable( join( '/', [__DIR__, '..', '..', '..'] ) )->load();

        $databaseMigrator = new DatabaseMigrator();
        $databaseMigrator->seedDatabase( $_ENV['DB_NAME'], $seed );

        return $this->makeResponse( ResponseCodes::OK );
    }
}
