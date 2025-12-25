<?php


namespace App\Structures\Media;

use Closure;
use Intervention\Image\Interfaces\ImageInterface;


class MediaVariant implements MediaVariantInterface
{
    use MediaProcessor, MediaSerializer;

    private ?Closure $condition = null;

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

    public function enabledFor(ImageInterface $image): bool {
        return $this->condition === null || ($this->condition)($image);
    }
}
