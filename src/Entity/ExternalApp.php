<?php

namespace App\Entity;

use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaCollectionList;
use App\Structures\Media\MediaVariant;
use App\Traits\Entity\LinksMedia;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Exception;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: 'App\Repository\ExternalAppRepository')]
#[UniqueEntity('name')]
#[Table]
#[UniqueConstraint(name: 'external_app_name_unique', columns: ['name'])]
class ExternalApp
{
    use LinksMedia;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'boolean')]
    private $active = true;
    #[ORM\Column(type: 'boolean')]
    private $maintenance = false;
    #[ORM\Column(type: 'string', length: 190)]
    private $name;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    #[ORM\JoinColumn(nullable: true)]
    private $owner = null;
    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private $secret;
    #[ORM\Column(type: 'string', length: 190)]
    private $url;
    #[ORM\Column(type: 'string', length: 190)]
    private $contact;
    #[ORM\Column(type: 'boolean', nullable: true)]
    private $testing = false;
    #[ORM\Column(type: 'boolean', nullable: true)]
    private $linkOnly = false;
    #[ORM\Column(type: 'blob', nullable: true)]
    private $image;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private $image_name;
    #[ORM\Column(type: 'string', length: 9, nullable: true)]
    private $image_format;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $devurl = null;

    #[ORM\Column(nullable: true)]
    private ?bool $wiki = null;
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getActive(): int
    {
        return $this->active;
    }
    public function setActive(int $active): self
    {
        $this->active = $active;

        return $this;
    }
    public function getMaintenance(): int
    {
        return $this->maintenance;
    }
    public function setMaintenance(int $maintenance): self
    {
        $this->maintenance = $maintenance;
        return $this;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
    public function getOwner(): ?User
    {
        return $this->owner;
    }
    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }
    public function getSecret(): ?string
    {
        return $this->secret;
    }
    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }
    public function getUrl(): string
    {
        return $this->url;
    }
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }
    public function getContact(): string
    {
        return $this->contact;
    }
    public function setContact(string $contact): self
    {
        $this->contact = $contact;

        return $this;
    }
    public function getTesting(): ?bool
    {
        return $this->testing;
    }
    public function setTesting(?bool $testing): self
    {
        $this->testing = $testing;

        return $this;
    }
    public function getLinkOnly(): ?bool
    {
        return $this->linkOnly;
    }
    public function setLinkOnly(?bool $linkOnly): self
    {
        $this->linkOnly = $linkOnly;

        return $this;
    }
    public function getImage()
    {
        return $this->image;
    }
    public function setImage($image): self
    {
        $this->image = $image;

        return $this;
    }
    public function getImageName(): ?string
    {
        return $this->image_name;
    }
    public function setImageName(?string $image_name): self
    {
        $this->image_name = $image_name;

        return $this;
    }
    public function getImageFormat(): ?string
    {
        return $this->image_format;
    }
    public function setImageFormat(?string $image_format): self
    {
        $this->image_format = $image_format;

        return $this;
    }

    public function getDevurl(): ?string
    {
        return $this->devurl;
    }

    public function setDevurl(?string $devurl): self
    {
        $this->devurl = $devurl;

        return $this;
    }

    public function isWiki(): ?bool
    {
        return $this->wiki;
    }

    public function setWiki(?bool $wiki): static
    {
        $this->wiki = $wiki;

        return $this;
    }

    protected static function defineMediaCollections(MediaCollectionList $list): void
    {
        $list->add( new MediaCollection('icon')
            ->singleFile()
            ->addVariant( new MediaVariant('default')
                              ->coverDown( 16, 16 )
                              ->toPng()
            )
            ->addVariant( new MediaVariant('default-hd')
                              ->conditional( fn( ImageInterface $image ) => $image->width() > 16 )
                              ->coverDown( 32, 32 )
                              ->toPng()
            )
            ->addVariant( new MediaVariant('default-uhd')
                              ->conditional( fn( ImageInterface $image ) => $image->width() > 32 )
                              ->coverDown( 64, 64 )
                              ->toWebp(quality: 100)
            )
        );
    }

    /**
     * @throws Exception
     */
    public function getMediaBasePath(): string
    {
        return "app/{$this->getPrimaryKey()}";
    }
}
