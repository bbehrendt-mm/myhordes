<?php


namespace App\Structures\Media;

use Closure;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

interface MediaVariantInterface
{
    public function enabledFor(ImageInterface $image): bool;

    public function process(ImageInterface $image): ImageInterface;

    public function performEncode(ImageInterface $image): EncodedImageInterface;

    public function serialize(): array;
}
