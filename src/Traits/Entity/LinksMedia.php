<?php

namespace App\Traits\Entity;

use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaCollectionList;
use Exception;

trait LinksMedia
{
    private static ?MediaCollectionList $mediaCollectionList = null;

    public static string $primaryKeyName = 'id';

    public function tryPrimaryKey(): ?string {
        $key = static::$primaryKeyName;
        $value = $this->$key ?? null;
        return $value === null ? null : "{$value}";
    }

    /**
     * @throws Exception
     */
    public function getPrimaryKey(): string {
        $value = $this->tryPrimaryKey();

        if ($value === null) {
            $class = static::class;
            throw new Exception("Cannot get primary key for $class.");
        }

        return $value;
    }

    public static function mediaCollections(): MediaCollectionList {
        if (static::$mediaCollectionList === null) {
            static::$mediaCollectionList = new MediaCollectionList();
            static::defineMediaCollections(static::$mediaCollectionList);
        }

        return static::$mediaCollectionList;
    }

    public static function mediaCollection(string $name): ?MediaCollection {
        return static::mediaCollections()->getCollection($name);
    }

    protected abstract static function defineMediaCollections(MediaCollectionList $list): void;

    public function getMediaPath( ): string {
        $prefix = $this->getMediaPathPrefix();
        return $prefix === null ? $this->getMediaBasePath() : "{$this->getMediaBasePath()}/{$prefix}";
    }

    public function getMediaPathPrefix( ): ?string {
        return null;
    }

    abstract public function getMediaBasePath( ): string;
}
