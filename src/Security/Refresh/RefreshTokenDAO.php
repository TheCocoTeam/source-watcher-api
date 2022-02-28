<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\Refresh;

use Coco\SourceWatcherApi\Framework\DAO;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Exception as CoreException;

class RefreshTokenDAO extends DAO
{
    /**
     * @param int $userId
     * @param string $value
     * @return RefreshToken
     * @throws FrameworkException
     */
    public function insertRefreshToken( int $userId, string $value ): RefreshToken {
        try {
            $sqlInstruction = "INSERT INTO refresh_token (user_id, value) VALUES (?, ?);";

            $connection = $this->getConnection();

            $statement = $connection->prepare( $sqlInstruction );
            $statement->bindValue( 1, $userId );
            $statement->bindValue( 2, $value );

            $affectedRows = $statement->executeStatement();

            $id = (int) $connection->lastInsertId();

            $refreshToken = new RefreshToken();
            $refreshToken->setId( $id );
            $refreshToken->setUserId( $userId );
            $refreshToken->setValue( $value );

            return $refreshToken;
        } catch ( FrameworkException $e ) {
            throw new FrameworkException( sprintf( "Something went wrong trying to get the connection: %s", $e->getMessage() ) );
        } catch ( CoreException $e ) {
            throw new FrameworkException( sprintf( "Something unexpected went wrong: %s", $e->getMessage() ) );
        }
    }

    public function getRefreshToken(): void {

    }

    public function deleteRefreshToken(): void {

    }
}
