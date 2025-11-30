<?php


namespace App\Structures;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

class MediaCollection
{
    private bool $single = false;

    private array $variants = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function singleFile($single = true): self {
        $this->single = $single;
        return $this;
    }

    public function isSingleFile(): bool {
        return $this->single;
    }

    /**
     * @param MediaVariant|ImageInterface|EncodedImageInterface $variant
     * @return $this
     * @noinspection PhpDocSignatureInspection
     */
    public function addVariant(MediaVariant $variant): self {
        $this->variants[ $variant->name ] = $variant;
        return $this;
    }

    /**
     * @return MediaVariant[]
     */
    public function getVariants(): array {
        return array_values( $this->variants );
    }

    public function getVariant(string $name): ?MediaVariant {
        return $this->variants[$name] ?? null;
    }
}
