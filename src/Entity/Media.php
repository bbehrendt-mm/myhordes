<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use App\Structures\Media\MediaVariantInterface;
use App\Traits\Entity\LinksMedia;
use ArrayHelpers\Arr;
use Doctrine\ORM\Mapping as ORM;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Index(name: 'media_morph_relation', columns: ['model_type','model_id'])]
#[ORM\Index(name: 'media_mima', columns: ['mime'])]
#[ORM\Index(name: 'media_filename', columns: ['filename'])]
#[ORM\UniqueConstraint(name: 'media_storage_path', columns: ['storage'])]
class Media
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $modelType = null;

    #[ORM\Column(length: 255)]
    private ?string $modelID = null;

    #[ORM\Column(length: 255)]
    private ?string $collection = null;

    #[ORM\Column(length: 1023)]
    private ?string $storage = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    private ?string $mime = null;

    #[ORM\Column]
    private array $conversions = [];

    #[ORM\Column]
    private array $meta = [];

    public ?ImageInterface $transientImage = null;
    /**
     * @var LinksMedia|null
     * @noinspection PhpDocFieldTypeMismatchInspection
     */
    public ?object $transientOwner = null;
    public bool $autoVariants = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }


    public function getModelType(): ?string
    {
        return $this->modelType;
    }

    public function setModelType(string $modelType): static
    {
        $this->modelType = $modelType;

        return $this;
    }

    public function getModelID(): ?string
    {
        return $this->modelID;
    }

    public function setModelID(string $modelID): static
    {
        $this->modelID = $modelID;

        return $this;
    }

    public function getCollection(): ?string
    {
        return $this->collection;
    }

    public function setCollection(string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function setStorage(string $storage): static
    {
        $this->storage = $storage;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function buildStoragePath(string $storage): static
    {
        return $this->setStorage("{$storage}/{$this->collection}/{$this->id}");
    }

    public function knowsConversion(string $conversion): bool
    {
        return Arr::get( $this->conversions, $conversion ) !== null;
    }

    public function hasConversion(string $conversion): bool
    {
        return Arr::get( $this->conversions, "$conversion.created", false ) !== false;
    }

    public function getTargetUrl(string $conversion, ?string $filename = null): ?string
    {
        if (!$this->knowsConversion($conversion)) return null;

        $filename = $filename ?? $this->filename;

        return "{$this->storage}/c/{$conversion}/{$filename}";
    }

    public function getUrl(?string $conversion = null): ?string
    {
        $basepath = $this->storage;
        if ($conversion !== null && $this->hasConversion($conversion))
            $basepath .= "/c/{$conversion}";

        return "{$basepath}/{$this->filename}";
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(string $mime): static
    {
        $this->mime = $mime;

        return $this;
    }

    public function getConversions(): array
    {
        return $this->conversions;
    }

    public function setConversions(array $conversions): static
    {
        $this->conversions = $conversions;

        return $this;
    }

    public function registerConversion(string|array $conversions, string|array $tag, ?array $variantData = null): static
    {
        if (!is_array($conversions)) $conversions = [$conversions];
        if (!is_array($tag)) $tag = [$tag];

        $data = $this->getConversions();

        $set = false;
        foreach ($conversions as $conversion) {
            if ($this->hasConversion($conversion)) continue;
            Arr::set($data, $conversion, [
                'created' => false,
                'tags' => $tag,
                ...($variantData !== null ? ['config' => $variantData] : []),
            ]);
            $set = true;
        }

        return $set ? $this->setConversions($data) : $this;
    }

    protected static function generateMetaFromImages(
        ?ImageInterface $rawImage,
        ?EncodedImageInterface $encodedImage,
        ?string $mime = null,
        ?int $size = null
    ): array {
        return [
            'width'  => $rawImage?->width(),
            'height' => $rawImage?->height(),
            'mime'   => $mime ?? $encodedImage?->mimetype(),
            'size'   => $size ?? $encodedImage?->size(),
        ];
    }

    public function setConversion(
        string $conversion,
        string $url,
        ?ImageInterface $rawImage = null,
        ?EncodedImageInterface $encodedImage = null,
        ?MediaVariantInterface $variant = null,
    ): static
    {
        $data = $this->getConversions();
        Arr::set($data, "$conversion.created", true);
        Arr::set($data, "$conversion.path", $url);
        Arr::set($data, "$conversion.meta", static::generateMetaFromImages($rawImage, $encodedImage));
        if ($variant)
            Arr::set($data, "$conversion.config", $variant->serialize());
        return $this->setConversions($data);
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function setMetaFromImage(?ImageInterface $rawImage = null, ?EncodedImageInterface $encodedImage = null, ?string $mime = null, ?int $size = null): static
    {
        return $this->setMeta(static::generateMetaFromImages($rawImage, $encodedImage, $mime, $size));
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    private function getSources(bool $includeOriginal = false, bool $sorted = true, string $tag = 'default'): array {
        $entries = array_map(
            fn(array $entry) => ['/storage/' . Arr::get($entry, 'path'), Arr::get($entry, 'meta.width')],
            array_filter($this->conversions, fn($entry) =>
                Arr::get($entry, 'created') &&
                Arr::get($entry, 'path') &&
                Arr::get($entry, 'meta.width') &&
                in_array( $tag, Arr::get($entry, 'tags', ['default']), true )
            )
        );

        if ($includeOriginal && Arr::get($this->meta, 'width'))
            $entries[] = ['/storage/' . $this->getUrl(), Arr::get($this->meta, 'width')];

        if ($sorted)
            usort($entries, fn(array $a, array $b) => $a[1] <=> $b[1]);

        return $entries;
    }

    public function getSourceSet(bool $includeOriginal = false, string $tag = 'default'): string {
        return implode(', ', array_map(
            fn(array $entry) => "{$entry[0]} {$entry[1]}w",
            $this->getSources( $includeOriginal, tag: $tag )
        ));
    }

    public function getSourceSetDPI(?int $baseSize, bool $includeOriginal = false, string $tag = 'default'): string {
        if ($baseSize === null) return $this->getSourceSet($includeOriginal);
        return implode(', ', array_map(
            function(array $entry) use ($baseSize) { return "{$entry[0]} " . round( $entry[1] / $baseSize, 1 ) . "x"; },
            $this->getSources( $includeOriginal, tag: $tag )
        ));
    }

    public function getSource(?int $expectedSize = PHP_INT_MAX, bool $includeOriginal = false, string $tag = 'default'): string {
        $entries = $this->getSources( $includeOriginal, tag: $tag );
        if (empty($entries)) return "";

        $entry = array_find( $entries, fn(array $entry) => $entry[1] >= ($expectedSize ?? PHP_INT_MAX) ) ?? $entries[array_key_last( $entries )];
        return $entry[0];
    }
}
