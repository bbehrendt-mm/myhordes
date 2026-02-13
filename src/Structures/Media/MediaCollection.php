<?php


namespace App\Structures\Media;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;

class MediaCollection
{
    private bool $single = false;
    private bool $archive = false;

    private array $variants = [];

    public function __construct(
        public readonly string $name,
        public readonly ?string $folder = null
    ) {}

    public function singleFile($single = true): self {
        $this->single = $single;
        return $this;
    }

    public function isSingleFile(): bool {
        return $this->single;
    }

    public function archiveCollection($archive = true): self {
        $this->archive = $archive;
        return $this;
    }

    public function isArchiveCollection(): bool {
        return $this->archive;
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
