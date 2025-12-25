<?php


namespace App\Structures\Media;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

class MediaCollection
{
    private bool $single = false;

    private array $variants = [];
    private array $conditional_variants = [];

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
    public function getVariants( ?ImageInterface $image = null ): array {
        return array_values( $image === null
                                 ? $this->variants
                                 : array_filter( $this->variants, fn( MediaVariantInterface $variant ) => $variant->enabledFor( $image ))
        );
    }

    public function getVariant(string $name): ?MediaVariant {
        return $this->variants[$name] ?? null;
    }
}
