<?php


namespace App\Structures\Media;

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
trait MediaProcessor
{
    private array $chain = [];
    private ?array $encode = null;

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
