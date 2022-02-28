<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security\User;

use Coco\SourceWatcherApi\Framework\DAO;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Exception as DbalException;
use Exception as CoreException;

class UserDAO extends DAO
{
    /**
     * @param string $username
     * @return User
     * @throws FrameworkException
     */
    public function getUser ( string $username ): User {
        $user = null;

        $connection = null;

        try {
            $connection = $this->getConnection();

            $sqlInstruction = "SELECT u.id, u.password, u.email FROM users u WHERE u.username = ?;";

            $statement = $connection->prepare( $sqlInstruction );
            $statement->bindValue( 1, $username );

            $resultSet = $statement->executeQuery();

            $user = new User();
            $user->setUsername( $username );

            while ( ( $row = $resultSet->fetchAssociative() ) !== false ) {
                $user->setId( intval( $row["id"] ) );
                $user->setPassword( $row["password"] );
                $user->setEmail( $row["email"] );
            }
        }
        catch ( DbalException $e ) {

        }
        catch ( DbalDriverException $e ) {

        }
        catch ( CoreException $e ) {
            throw new FrameworkException( sprintf( "Something unexpected went wrong: %s", $e->getMessage() ) );
        }
        finally {
            $connection->close();
        }

        return $user;
    }
}
