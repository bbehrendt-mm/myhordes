<?php

namespace App\Messages\Media;

use App\Messages\AsyncMessageMediaInterface;

readonly class CreateMediaVariantMessage implements AsyncMessageMediaInterface
{
    public function __construct(
        public string $uuid,
        public string $variantName,
        public array $variantData
    ) { }
}
