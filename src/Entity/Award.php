<?php


namespace App\Entity;

use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaCollectionList;
use App\Structures\Media\MediaVariant;
use App\Traits\Entity\LinksMedia;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: 'App\Repository\AwardRepository')]
class Award
{
    use LinksMedia;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: 'awards')]
    #[ORM\JoinColumn(nullable: false)]
    private $user;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\AwardPrototype')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private $prototype;
    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private $customTitle;

    private ?User $cached_user = null;

    public function getUser(): ?User {
        return  $this->user;
    }
    public function getId(): ?int {
        return $this->id;
    }
    public function getPrototype(): ?AwardPrototype {
        return $this->prototype;
    }
    public function setPrototype(AwardPrototype $value): self {
        $this->prototype = $value;
        return $this;
    }
    public function setUser(?User $value): self {
        if ($value) $this->cached_user = $value;
        elseif (!$this->cached_user) $this->cached_user = $this->user ?? null;
        $this->user = $value;
        return $this;
    }
    public function getCustomTitle(): ?string
    {
        return $this->customTitle;
    }
    public function setCustomTitle(?string $customTitle): self
    {
        $this->customTitle = $customTitle;

        return $this;
    }

    protected static function defineMediaCollections(MediaCollectionList $list): void
    {
        $list->add( new MediaCollection('icon')
            ->singleFile()
            ->addVariant( new MediaVariant('default-still')
                ->conditional( fn(ImageInterface $image) => !$image->isAnimated() )
                ->coverDown( 16, 16 )
                ->toPng()
            )
            ->addVariant( new MediaVariant('default-animated')
                ->conditional( fn(ImageInterface $image) => $image->isAnimated() )
                ->coverDown( 16, 16 )
                ->toGif()
            )
            ->addVariant( new MediaVariant('default-hd-still')
                ->minResolution( width: 17 )
                ->conditional( fn( ImageInterface $image ) => !$image->isAnimated() )
                ->coverDown( 32, 32 )
                ->toPng()
            )
            ->addVariant( new MediaVariant('default-hd-animated')
                ->minResolution( width: 17 )
                ->conditional( fn( ImageInterface $image ) => $image->isAnimated() )
                ->coverDown( 32, 32 )
                ->toWebp(quality: 90)
            )
            ->addVariant( new MediaVariant('default-uhd-still')
                ->minResolution( width: 33 )
                ->conditional( fn( ImageInterface $image ) => !$image->isAnimated() )
                ->coverDown( 64, 64 )
                ->toWebp(quality: 100)
            )
            ->addVariant( new MediaVariant('default-uhd-animated')
                ->minResolution( width: 33 )
                ->conditional( fn( ImageInterface $image ) => $image->isAnimated() )
                ->coverDown( 64, 64 )
                ->toWebp(quality: 90)
            )
        );
    }

    /**
     * @throws Exception
     */
    public function getMediaBasePath(): string
    {
        $user = $this->getUser() ?? $this->cached_user;
        return "user/{$user->getId()}/award/{$this->getPrimaryKey()}";
    }
}
