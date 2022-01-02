<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Database;

use Coco\SourceWatcherApi\Framework\DAO;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Exception as CoreException;

/**
 * Class DbConnectionTypeDAO
 * @package Coco\SourceWatcherApi\Database
 */
class DbConnectionTypeDAO extends DAO
{
    /**
     * @param int|null $id
     * @return array
     * @throws FrameworkException
     */
    public function getDbConnectionType( ?int $id = null ): array
    {
        $result = [];

        try {
            $connection = $this->getConnection();

            if ( empty( $id ) ) {
                $sqlInstruction = 'SELECT ct.id, ct.driver FROM db_connection_type ct;';
                $statement = $connection->prepare( $sqlInstruction );
            } else {
                $sqlInstruction = 'SELECT ct.id, ct.driver FROM db_connection_type ct WHERE ct.id = ?;';
                $statement = $connection->prepare( $sqlInstruction );
                $statement->bindValue( 1, $id );
            }

            $resultSet = $statement->executeQuery();

            while ( ( $row = $resultSet->fetchAssociative() ) !== false ) {
                $connectionType = new DbConnectionType();
                $connectionType->setId( intval( $row['id'] ) );
                $connectionType->setDriver( $row['driver'] );

                $result[] = $connectionType;
            }
        } catch ( FrameworkException $e ) {
            throw new FrameworkException( sprintf( 'Something went wrong trying to get the connection: %s', $e->getMessage() ) );
        } catch ( CoreException $e ) {
            throw new FrameworkException( sprintf( 'Something unexpected went wrong: %s', $e->getMessage() ) );
        }

        return $result;
    }
}
