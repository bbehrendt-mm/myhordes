<?php


namespace App\Structures;

use Closure;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * @mixin ImageInterface
 * @method self scale(int $width, int $height)
 * @method self scaleDown(int $width, int $height)
 * @method self cover(int $width, int $height, string $position = 'center')
 * @method self coverDown(int $width, int $height, string $position = 'center')
 * @method self toWebp(mixed ...$options)
 * @method self toPng(mixed ...$options)
 */
class MediaVariant
{
    private array $chain = [];
    private ?array $encode = null;

    private ?Closure $condition = null;

    public function __construct(
        public readonly string $name,
    ) {}

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

    public function __call(string $name, array $arguments)
    {
        if ($name === 'encode' || str_starts_with( $name, 'to' ))
            $this->encode = [$name, $arguments];
        else $this->chain[] = [$name, $arguments];
        return $this;
    }

    public function process(ImageInterface $image): ImageInterface {
        foreach ($this->chain as [$function, $arguments])
            $image = $image->{$function}(...$arguments);

        return $image;
    }

    public function performEncode(ImageInterface $image): EncodedImageInterface {
        if ($this->encode !== null) return $image->{$this->encode[0]}(...$this->encode[1]);
        else return $image->encode();
    }

}
