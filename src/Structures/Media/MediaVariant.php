<?php


namespace App\Structures\Media;

use Closure;
use Intervention\Image\Interfaces\ImageInterface;


class MediaVariant implements MediaVariantInterface
{
    use MediaProcessor, MediaSerializer;

    private ?Closure $condition = null;

    private int $min_width = 0;
    private int $min_height = 0;

    public readonly array $tags;

    public function __construct(
        public readonly string $name,
        string|array $tag = ['default']
    ) {
        $this->tags = is_array($tag) ? $tag : [$tag];
    }

    /**
     * @param null|Closure(ImageInterface):bool $condition
     * @return MediaVariant
     */
    public function conditional(Closure|null $condition): self {
        $this->condition = $condition;
        return $this;
    }

    public function minResolution(?int $width = null, ?int $height = null): self {
        $this->min_width = $width ?? $this->min_width;
        $this->min_height = $height ?? $this->min_height;
        return $this;
    }

    public function enabledForResolution(int $width, int $height): bool {
        return $width >= $this->min_width && $height >= $this->min_height;
    }

    public function enabledFor(ImageInterface $image): bool {
        return $this->enabledForResolution($image->width(), $image->height()) && ($this->condition === null || ($this->condition)($image));
    }
}
