<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Core\Item;

use JsonSerializable;

class Item implements JsonSerializable
{
    private ?int $id = null;
    private string $name = '';
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function jsonSerialize(): array
    {
        $data = ['name' => $this->name];
        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        $data['description'] = $this->description;

        return $data;
    }
}
