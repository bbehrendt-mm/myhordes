<?php

namespace App\Traits\Entity;

use App\Entity\Media;
use App\Entity\Morph;
use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaCollectionList;
use Exception;

trait LinksMedia
{
    use DoctrineExtension, LinksMorph {
        LinksMorph::getMorphTo as private parentGetMorphTo;
    }

    /**
     * @return array<class-string<Morph>>
     */
    public static function getMorphTo(): array {
        return array_unique([
            ...self::parentGetMorphTo(),
            Media::class
        ]);
    }

    private static ?MediaCollectionList $mediaCollectionList = null;

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
