<?php

namespace App\Traits\Entity;

use App\Entity\Morph;
use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaCollectionList;
use Exception;

trait LinksMorph
{
    use DoctrineExtension;
    protected static string $primaryKeyName = 'id';

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

    /**
     * @return array<class-string<Morph>>
     */
    public static function getMorphTo(): array {
        /** @noinspection PhpUndefinedFieldInspection */
        return static::$morphsTo ?? [];
    }
}
