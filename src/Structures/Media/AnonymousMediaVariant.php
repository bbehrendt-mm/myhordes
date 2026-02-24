<?php


namespace App\Structures\Media;

use ArrayHelpers\Arr;
use Intervention\Image\Interfaces\ImageInterface;

class AnonymousMediaVariant implements MediaVariantInterface
{
    use MediaProcessor, MediaSerializer;

    public function __construct(
        array $serialized,
    ) {
        $this->chain = Arr::get($serialized, 'chain');
        $this->encode = Arr::get($serialized, 'encode');
    }

    public function enabledFor(ImageInterface $image): true {
        return true;
    }

}
