<?php

namespace App\Structures\Media;

use App\Entity\Media;
use ArrayHelpers\Arr;

/**
 * Represents a media conversion with associated metadata.
 * @property-read int $width
 * @property-read int $height
 * @property-read int $frames
 * @property-read string $mime
 * @property-read int $size
 * @property-read string $url
 */
readonly class MediaConversion
{

    public function __construct(
        public Media $media,
        public string $conversion,
        public array $meta,
        public array $tags,
    ) {}

    public function __get(string $name)
    {
        return match ($name) {
            'width', 'height', 'frames', 'size' => Arr::get($this->meta, $name, 0),
            'mime' => Arr::get($this->meta, 'mime', 'application/octet-stream'),
            'url'  => "/storage/{$this->media->getUrl($this->conversion)}",
            default => null,
        };
    }

    public function isTaggedAs(string $tag): bool {
        return in_array($tag, $this->tags, true);
    }
}
