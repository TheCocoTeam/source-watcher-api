<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Core\Item;

use Coco\SourceWatcherApi\Framework\DAO;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Exception as CoreException;

class ItemDAO extends DAO
{
    /**
     * @return Item[]
     * @throws FrameworkException
     */
    public function getList(): array
    {
        $result = [];
        try {
            $connection = $this->getConnection();
            $sql = 'SELECT id, name, description FROM item ORDER BY id';
            $statement = $connection->prepare($sql);
            $resultSet = $statement->executeQuery();
            while (($row = $resultSet->fetchAssociative()) !== false) {
                $result[] = $this->rowToItem($row);
            }
        } catch (FrameworkException $e) {
            throw $e;
        } catch (CoreException $e) {
            throw new FrameworkException(
                sprintf('Something went wrong getting item list: %s', $e->getMessage())
            );
        }
        return $result;
    }

    /**
     * @throws FrameworkException
     */
    public function getById(int $id): ?Item
    {
        try {
            $connection = $this->getConnection();
            $sql = 'SELECT id, name, description FROM item WHERE id = ?';
            $statement = $connection->prepare($sql);
            $statement->bindValue(1, $id);
            $resultSet = $statement->executeQuery();
            $row = $resultSet->fetchAssociative();
            return $row !== false ? $this->rowToItem($row) : null;
        } catch (FrameworkException $e) {
            throw $e;
        } catch (CoreException $e) {
            throw new FrameworkException(
                sprintf('Something went wrong getting item: %s', $e->getMessage())
            );
        }
    }

    /**
     * @throws FrameworkException
     */
    public function create(string $name, ?string $description = null): Item
    {
        try {
            $connection = $this->getConnection();
            $sql = 'INSERT INTO item (name, description) VALUES (?, ?)';
            $statement = $connection->prepare($sql);
            $statement->bindValue(1, $name);
            $statement->bindValue(2, $description);
            $statement->executeStatement();
            $id = (int) $connection->lastInsertId();
            $item = new Item();
            $item->setId($id);
            $item->setName($name);
            $item->setDescription($description);
            return $item;
        } catch (CoreException $e) {
            throw new FrameworkException(
                sprintf('Something went wrong creating item: %s', $e->getMessage())
            );
        }
    }

    /**
     * @throws FrameworkException
     */
    public function update(int $id, string $name, ?string $description = null): bool
    {
        try {
            $connection = $this->getConnection();
            $sql = 'UPDATE item SET name = ?, description = ? WHERE id = ?';
            $statement = $connection->prepare($sql);
            $statement->bindValue(1, $name);
            $statement->bindValue(2, $description);
            $statement->bindValue(3, $id);
            $statement->executeStatement();
            return $statement->rowCount() > 0;
        } catch (CoreException $e) {
            throw new FrameworkException(
                sprintf('Something went wrong updating item: %s', $e->getMessage())
            );
        }
    }

    /**
     * @throws FrameworkException
     */
    public function delete(int $id): bool
    {
        try {
            $connection = $this->getConnection();
            $sql = 'DELETE FROM item WHERE id = ?';
            $statement = $connection->prepare($sql);
            $statement->bindValue(1, $id);
            $statement->executeStatement();
            return $statement->rowCount() > 0;
        } catch (CoreException $e) {
            throw new FrameworkException(
                sprintf('Something went wrong deleting item: %s', $e->getMessage())
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToItem(array $row): Item
    {
        $item = new Item();
        $item->setId((int) $row['id']);
        $item->setName((string) $row['name']);
        $item->setDescription(isset($row['description']) ? (string) $row['description'] : null);
        return $item;
    }
}
