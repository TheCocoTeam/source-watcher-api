<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\JWKS;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class JWKSHelper
{
    /**
     * @var Logger
     */
    private Logger $log;

    /**
     * JWKSHelper constructor.
     */
    public function __construct()
    {
        $logPath = join( '/', [__DIR__, '..', '..', 'logs', time() . '.log'] );

        $this->log = new Logger( JWKSHelper::class );
        $this->log->pushHandler( new StreamHandler( $logPath, Logger::ERROR ) );
    }

    /**
     *
     */
    public const ALGORITHM = 'RS256';

    /**
     * @return string
     */
    public function getPrivateKey(): string {
        return file_get_contents( join( '/', [__DIR__, '..', 'keys', 'current', 'private.pem'] ) );
    }

    /**
     * @return string
     */
    public function getPublicKey(): string {
        return file_get_contents( join( '/', [__DIR__, '..', 'keys', 'current', 'public.pem'] ) );
    }

    /**
     * Allows returning the JSON Web Key.
     * @return array
     */
    public function getJWK(): array
    {
        $details = openssl_pkey_get_details(
            openssl_pkey_get_public(
                file_get_contents(
                    join( '/', [__DIR__, '..', 'keys', 'current', 'public.pem'] )
                )
            )
        );

        $rsa = $details['rsa'];

        return [
            'kty' => 'RSA',
            'e' => base64_encode( $rsa['e'] ),
            'use' => 'sig',
            'kid' => sha1( $rsa['n'] ),
            'alg' => self::ALGORITHM,
            'n' => base64_encode( $rsa['n'] )
        ];
    }

    /**
     * @param string $jwt
     * @return bool
     */
    public function jwtIsValid( string $jwt ): bool {
        try {
            $jwtDecoded = JWT::decode( $jwt, new Key( $this->getPublicKey(), self::ALGORITHM ) );

            $iss = $jwtDecoded->iss;
            $iss_is_valid = !empty( $iss ) && $iss === $_SERVER['HTTP_HOST'];

            if ( !$iss_is_valid ) {
                return false;
            }

            $aud = $jwtDecoded->aud;
            $aud_is_valid = !empty( $aud ) && $aud === $_SERVER['HTTP_HOST'];

            if ( !$aud_is_valid ) {
                return false;
            }

            $iat = $jwtDecoded->iat;
            $iat_is_valid = !empty( $iat ) && $iat < time();

            if ( !$iat_is_valid ) {
                return false;
            }

            $eat = $jwtDecoded->eat;
            $eat_is_valid = !empty( $eat ) && $eat > time();

            if ( !$eat_is_valid ) {
                //return false;

                throw new TokenExpiredException();
            }

            //$data = $jwtDecoded->data;

            //$userId = $data['userId'];
        }
        catch ( Exception $exception ) {
            $this->log->error(
                sprintf(
                    'Something went wrong trying to verify if the JWT is valid: %s', $exception->getMessage()
                )
            );

            return false;
        }

        return true;
    }

    public function getRefreshToken( int $length = 10 ): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen( $characters );
        $randomString = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $randomString .= $characters[rand( 0, $charactersLength - 1 )];
        }

        return $randomString;
    }
}
