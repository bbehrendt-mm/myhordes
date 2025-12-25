<?php


namespace App\Structures\Media;

class MediaCollectionList
{
    private array $list = [];

    public function add(MediaCollection $collection): self {
        $this->list[ $collection->name ] = $collection;
        return $this;
    }

    public function getCollection(string $name): ?MediaCollection {
        return $this->list[ $name ] ?? null;
    }
}
