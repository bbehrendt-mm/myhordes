<?php


namespace App\Structures;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * @mixin ImageInterface
 */
class MediaVariant
{
    private array $chain = [];
    private ?array $encode = null;

    public function __construct(
        public readonly string $name,
    ) {}

    public function __call(string $name, array $arguments)
    {
        if ($name === 'encode' || (str_starts_with( $name, 'to' ) && count($arguments) === 0))
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
