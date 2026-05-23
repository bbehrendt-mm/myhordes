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

abstract class Morph
{
    #[ORM\Column(length: 255)]
    protected ?string $modelType = null;

    #[ORM\Column(length: 255)]
    protected ?string $modelID = null;

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

    public function chainDelete(object $from): bool {
        return true;
    }
}
