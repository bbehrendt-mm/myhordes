<?php

namespace App\Entity;

use App\Messages\Media\CreateMediaVariantMessage;
use App\Repository\MediaRepository;
use App\Structures\Media\AnonymousMediaVariant;
use App\Structures\Media\MediaConversion;
use App\Structures\Media\MediaVariantInterface;
use App\Traits\Entity\LinksMedia;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $collectionFolder = null;

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

    public array $relatedCaches = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inCollectionSince = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deleteAt = null;

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

    public function getMangledClassName(): string {
        return md5($this->getModelType());
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

    public function getCollectionFolder(): ?string
    {
        return $this->collectionFolder;
    }

    public function setCollectionFolder(?string $collectionFolder): static
    {
        $this->collectionFolder = $collectionFolder;

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
        $folder = $this->getCollectionFolder() ?? $this->getCollection();
        return $this->setStorage("{$storage}/{$folder}/{$this->id}");
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

    public function getConversionUrl(string $conversion): ?string
    {
        return $this->hasConversion($conversion)
            ? Arr::get( $this->conversions, "$conversion.path", null )
            : null;
    }

    public function getUrl(?string $conversion = null): ?string
    {
        if ($conversion !== null && $this->hasConversion($conversion))
            return Arr::get( $this->conversions, "$conversion.path", null );

        return "{$this->storage}/{$this->filename}";
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

    public function unregisterConversion(string|array $conversions): static
    {
        if (!is_array($conversions)) $conversions = [$conversions];

        $data = $this->getConversions();

        $new = [];
        $set = false;
        foreach ($data as $conversion => $conversionData) {
            if ($this->hasConversion($conversion)) continue;
            if (in_array( $conversion, $conversions ))
                $set = true;
            else Arr::set($new, $conversion , $conversionData);
        }

        return $set ? $this->setConversions($new) : $this;
    }

    /**
     * @param string|array $conversions
     * @param callable(AnonymousMediaVariant): void $callback
     * @return Media
     */
    public function modifyConversion(string|array $conversions, callable $callback): static
    {
        if (!is_array($conversions)) $conversions = [$conversions];
        $data = $this->getConversions();

        $set = false;
        foreach ($conversions as $conversion) {
            $config = Arr::get($data, "$conversion.config", null);
            if (!$config) continue;

            $temp = new AnonymousMediaVariant($config);
            $callback($temp);
            Arr::set($data, "$conversion.config", $temp->serialize());
            $set = true;
        }

        return $set ? $this->setConversions($data) : $this;
    }

    public function tagConversion(string|array $conversions, string|array $tag): static
    {
        if (!is_array($conversions)) $conversions = [$conversions];
        if (!is_array($tag)) $tag = [$tag];

        $data = $this->getConversions();

        $set = false;
        foreach ($conversions as $conversion)
            Arr::set( $data, "{$conversion}.tags", array_unique([
                ...array_values(Arr::get($data, "{$conversion}.tags", [])),
                ...array_values($tag),
            ]));

        return $this->setConversions($data);
    }

    /**
     * @param string|null $name
     * @return CreateMediaVariantMessage[]
     */
    public function pendingConversionMessages(?string $name = null): array {
        $data = $this->getConversions();

        $entries = array_filter( $name !== null ? [$name => Arr::get($data, $name, [])] : $data,
            fn(array $entry) => !Arr::get($entry, 'created', false) && !empty(Arr::get($entry, 'config', null))
        );

        return array_map( fn(string $key, array $entry) => new CreateMediaVariantMessage(
            $this->id, $key, Arr::get($entry, 'config', null)
        ), array_keys($entries), $entries  );
    }

    /**
     * @param array|null $includeTags
     * @param array|null $excludeTags
     * @return string[]
     */
    public function findConversions(
        ?array $includeTags = null,
        ?array $excludeTags = null
    ): array {
        $tags = new ArrayCollection( $this->getConversions() );

        return $tags->filter( fn(array $entry) =>
            ($includeTags === null || array_intersect($includeTags, Arr::get( $entry, 'tags', [] ))) &&
            ($excludeTags === null || !array_intersect($excludeTags, Arr::get( $entry, 'tags', [] )))
        )->getKeys();
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
            'frames' => $rawImage?->count(),
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

    public function getConversion(string $conversion): ?MediaConversion
    {
        return $this->hasConversion($conversion)
            ? new MediaConversion($this, $conversion,
                                  Arr::get($this->getConversions(), "$conversion.meta", []),
                                  Arr::get($this->getConversions(), "$conversion.config", []),
                                  Arr::get($this->getConversions(), "$conversion.tags", ['default'])
            )
            : null;
    }

    /**
     * @return MediaConversion[]
     */
    public function getConversionsByTag(string $tag, bool $include_pending = false): array
    {
        $conversions = $this->getRawConversionsByTag($tag, $include_pending);
        return array_map(
            fn(string $conversion, array $data) => new MediaConversion($this, $conversion,
                                                                       Arr::get($data, 'meta', []),
                                                                       Arr::get($data, 'config', []),
                                                                       Arr::get($data, 'tags', ['default'])
            ),
            array_keys($conversions), $conversions
        );
    }

    /**
     * @param string $tag
     * @param int|null $expectedSize
     * @param bool $include_pending
     * @return MediaConversion|null
     */
    public function getLargestConversionByTag(string $tag, ?int $expectedSize = PHP_INT_MAX, bool $include_pending = false): ?MediaConversion
    {
        $conversions = $this->getConversionsByTag( $tag, $include_pending );
        usort( $conversions, fn(MediaConversion $a, MediaConversion $b) => $a->width <=> $b->width );

        return
            array_find( $conversions, fn(MediaConversion $entry) => $entry->width >= ($expectedSize ?? PHP_INT_MAX) ) ??
            $conversions[array_key_last( $conversions )] ??
            null;
    }



    protected function getRawConversionsByTag(string $tag, bool $include_pending = false): array
    {
        return array_filter($this->conversions, fn($entry) =>
            ($include_pending || (Arr::get($entry, 'created') &&
            Arr::get($entry, 'path') &&
            Arr::get($entry, 'meta.width'))) &&
            in_array( $tag, Arr::get($entry, 'tags', ['default']), true )
        );
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getMetaKey(string $key, mixed $default = null): mixed
    {
        return Arr::get( $this->meta ?? [], $key, $default);
    }

    public function setMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function setMetaKey(string $key, mixed $data): static
    {
        $meta = $this->getMeta();
        Arr::set($meta, $key, $data);
        return $this->setMeta( $meta );
    }

    public function pushMetaKey(string $key, mixed $data, bool $unique = false): static
    {
        $array = $this->getMetaKey($key, []);
        if (!is_array($array)) return $this;
        $array[] = $data;
        if ($unique) $array = array_unique($array);
        return $this->setMetaKey( $key, $array );
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
            $this->getRawConversionsByTag($tag)
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

    public function getInCollectionSince(): ?\DateTimeImmutable
    {
        return $this->inCollectionSince;
    }

    public function setInCollectionSince(?\DateTimeImmutable $inCollectionSince): static
    {
        $this->inCollectionSince = $inCollectionSince;

        return $this;
    }

    public function getDeleteAt(): ?\DateTimeImmutable
    {
        return $this->deleteAt;
    }

    public function setDeleteAt(?\DateTimeImmutable $deleteAt): static
    {
        $this->deleteAt = $deleteAt;

        return $this;
    }
}
