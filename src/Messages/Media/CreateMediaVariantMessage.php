<?php

namespace App\Messages\Media;

use App\Messages\AsyncMessageMediaInterface;

class CreateMediaVariantMessage implements AsyncMessageMediaInterface
{
    protected array $relatedCaches = [];

    public function __construct(
        readonly public string $uuid,
        readonly public string $variantName,
        readonly public array $variantData
    ) { }

    public function appendToRelatedCaches(string|array $tags): self {
        if (!is_array($tags)) $tags = [$tags];
        $this->relatedCaches = [
            ...$this->relatedCaches,
            ...array_values($tags),
        ];
        return $this;
    }

    public function getRelatedCaches(): array {
        return $this->relatedCaches;
    }
}
