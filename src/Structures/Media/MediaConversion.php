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
 * @property-read ?array $crop
 */
readonly class MediaConversion
{

    public function __construct(
        public Media $media,
        public string $conversion,
        public array $meta,
        public array $config,
        public array $tags,
    ) {}

    public function getUrl(string $base = ''): ?string {
        $url = $this->media->getConversionUrl($this->conversion);
        return empty($url) ? null : "$base/storage/$url";
    }

    private function getInitialCrop(): ?array {
        $firstOp = Arr::get($this->config, 'chain.0', []);
        if (Arr::get($firstOp, '0') !== 'crop') return null;

        return [
            'x' => (int)Arr::get($firstOp, '1.2'),
            'y' => (int)Arr::get($firstOp, '1.3'),
            'width' => (int)Arr::get($firstOp, '1.0'),
            'height' => (int)Arr::get($firstOp, '1.1')
        ];
    }

    public function __get(string $name)
    {
        return match ($name) {
            'width', 'height', 'frames', 'size' => Arr::get($this->meta, $name, 0),
            'mime' => Arr::get($this->meta, 'mime', 'application/octet-stream'),
            'url'  => $this->getUrl(),
            'crop' => $this->getInitialCrop(),
            default => null,
        };
    }

    public function isTaggedAs(string $tag): bool {
        return in_array($tag, $this->tags, true);
    }
}
