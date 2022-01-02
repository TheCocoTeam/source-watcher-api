<?php

namespace Coco\SourceWatcherApi\Database;

use JsonSerializable;

class DbConnectionType implements JsonSerializable
{
    private int $id;
    private string $driver;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * @param string $driver
     */
    public function setDriver(string $driver): void
    {
        $this->driver = $driver;
    }

    public function jsonSerialize()
    {
        return ['id' => $this->id, 'driver' => $this->driver];
    }
}
