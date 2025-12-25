<?php


namespace App\Structures\Media;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

trait MediaSerializer
{
    public function serialize(): array {
        return [
            'chain' => $this->chain,
            'encode' => $this->encode,
        ];
    }
}
