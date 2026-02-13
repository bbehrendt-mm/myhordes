<?php


namespace App\Structures\Media;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * @mixin ImageInterface
 * @method self resize(int $width, ?int $height = null)
 * @method self resizeDown(int $width, ?int $height = null)
 * @method self scale(int $width, ?int $height = null)
 * @method self scaleDown(int $width, ?int $height = null)
 * @method self cover(int $width, int $height, string $position = 'center')
 * @method self coverDown(int $width, int $height, string $position = 'center')
 * @method self crop(int $width, int $height, int $offset_x = 0, int $offset_y = 0, mixed $background = 'ffffff', string $position = 'top-left')
 * @method self toWebp(mixed ...$options)
 * @method self toPng(mixed ...$options)
 * @method self toGif(mixed ...$options)
 * @method self toAvif(mixed ...$options)
 */
trait MediaProcessor
{
    private array $chain = [];
    private ?array $encode = null;

    private bool $prepend_mode = false;

    public function prepend(): self {
        $this->prepend_mode = true;
        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        if ($name === 'encode' || str_starts_with( $name, 'to' ))
            $this->encode = [$name, $arguments];
        elseif (!$this->prepend_mode) $this->chain[] = [$name, $arguments];
        else {
            array_unshift($this->chain, [$name, $arguments]);
            $this->prepend_mode = false;
        }
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
