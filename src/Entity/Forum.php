<?php

namespace App\Entity;

use App\Enum\ForumType;
use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaCollectionList;
use App\Structures\Media\MediaVariant;
use App\Traits\Entity\LinksMedia;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;
use Exception;

#[ORM\Entity(repositoryClass: 'App\Repository\ForumRepository')]
class Forum
{
    use LinksMedia;

    const int ForumTypeDefault = 0;
    const int ForumTypeElevated = 1;
    const int ForumTypeMods = 2;
    const int ForumTypeAdmins = 3;
    const int ForumTypeCustom = 4;
    const int ForumTypeAnimac = 5;
    const int ForumTypeDev = 6;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\OneToOne(targetEntity: Town::class, inversedBy: 'forum', cascade: ['persist'])]
    private ?Town $town = null;
    #[ORM\Column(type: 'string', length: 128)]
    private ?string $title;
    #[ORM\OneToMany(targetEntity: Thread::class, mappedBy: 'forum', cascade: ['persist', 'remove'])]
    private Collection $threads;
    #[ORM\Column(type: 'integer', nullable: true, enumType: ForumType::class)]
    private ?ForumType $type = null;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;
    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $icon;
    #[ORM\ManyToMany(targetEntity: ThreadTag::class)]
    private Collection $allowedTags;
    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $worldForumLanguage;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $worldForumSorting;

    #[ORM\OneToMany(targetEntity: ForumTitle::class, mappedBy: 'forum', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $titles;

    #[ORM\ManyToOne(inversedBy: 'forums')]
    private ?ForumGroup $forumGroup = null;

    #[ORM\Column(nullable: true)]
    private ?array $config = null;
    public function __construct()
    {
        $this->threads = new ArrayCollection();
        $this->allowedTags = new ArrayCollection();
        $this->titles = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getTown(): ?Town
    {
        return $this->town;
    }
    public function setTown(?Town $town): self
    {
        $this->town = $town;

        return $this;
    }
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLocalizedTitle(string $lang): ?string {
        $entity = $this->getTitles()->matching( new Criteria(accessRawFieldValues: true)
            ->where( new Comparison( 'language', Comparison::EQ, $lang )  )
        )->first();
        return $entity ? $entity->getTitle() : $this->getTitle();
    }

    public function getLocalizedSlug(string $lang): ?string {
        $base = $this->getLocalizedTitle($lang);
        $slug = implode('',
            array_map(
                fn(string $s) => mb_substr($s, 0, 1),
                explode( ' ', $base )
            )
        );

        return mb_strlen($slug) === 1 ? mb_substr( $base, 0, 3 ) : $slug;
    }

    public function getLocalizedDescription(string $lang): ?string {
        $entity = $this->getTitles()->matching( new Criteria(accessRawFieldValues: true)
            ->where( new Comparison( 'language', Comparison::EQ, $lang )  )
        )->first();
        return $entity ? $entity->getDescription() : $this->getDescription();
    }

    /**
     * @return ArrayCollection<int, Thread>|PersistentCollection<int, Thread>
     */
    public function getThreads(): ArrayCollection|PersistentCollection
    {
        return $this->threads;
    }
    public function addThread(Thread $thread): self
    {
        if (!$this->threads->contains($thread)) {
            $this->threads[] = $thread;
            $thread->setForum($this);
        }

        return $this;
    }
    public function removeThread(Thread $thread): self
    {
        if ($this->threads->contains($thread)) {
            $this->threads->removeElement($thread);
            // set the owning side to null (unless already changed)
            if ($thread->getForum() === $this) {
                $thread->setForum(null);
            }
        }

        return $this;
    }
    public function getType(): ?ForumType
    {
        return $this->type;
    }
    public function setType(?ForumType $type): self
    {
        $this->type = $type;

        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }
    /**
     * @return ArrayCollection<int, ThreadTag>|PersistentCollection<int, ThreadTag>
     */
    public function getAllowedTags(): ArrayCollection|PersistentCollection
    {
        return $this->allowedTags;
    }
    public function addAllowedTag(ThreadTag $allowedTag): self
    {
        if (!$this->allowedTags->contains($allowedTag)) {
            $this->allowedTags[] = $allowedTag;
        }

        return $this;
    }
    public function removeAllowedTag(ThreadTag $allowedTag): self
    {
        $this->allowedTags->removeElement($allowedTag);

        return $this;
    }
    public function getWorldForumLanguage(): ?string
    {
        return $this->worldForumLanguage;
    }
    public function setWorldForumLanguage(?string $worldForumLanguage): self
    {
        $this->worldForumLanguage = $worldForumLanguage;

        return $this;
    }
    public function getWorldForumSorting(): ?int
    {
        return $this->worldForumSorting;
    }
    public function setWorldForumSorting(?int $worldForumSorting): self
    {
        $this->worldForumSorting = $worldForumSorting;

        return $this;
    }

    /**
     * @return Collection<int, ForumTitle>
     */
    public function getTitles(): Collection
    {
        return $this->titles;
    }

    public function addTitle(ForumTitle $title): static
    {
        if (!$this->titles->contains($title)) {
            $this->titles->add($title);
            $title->setForum($this);
        }

        return $this;
    }

    public function removeTitle(ForumTitle $title): static
    {
        if ($this->titles->removeElement($title)) {
            // set the owning side to null (unless already changed)
            if ($title->getForum() === $this) {
                $title->setForum(null);
            }
        }

        return $this;
    }

    public function getForumGroup(): ?ForumGroup
    {
        return $this->forumGroup;
    }

    public function setForumGroup(?ForumGroup $forumGroup): static
    {
        $this->forumGroup = $forumGroup;

        return $this;
    }

    protected static function defineMediaCollections(MediaCollectionList $list): void
    {
        $list->add( new MediaCollection('icon')
            ->singleFile()
            ->addVariant( new MediaVariant('web')
                ->coverDown( 100, 30 )
                ->toWebp()
            )
        );
    }

    public function getMediaPathPrefix(): ?string
    {
        return "meta";
    }

    /**
     * @throws Exception
     */
    public function getMediaBasePath(): string
    {
        return "forum/{$this->getPrimaryKey()}";
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;

        return $this;
    }

    private function setConfigKey(string $key, mixed $value): static
    {
        $config = $this->getConfig() ?? [];
        Arr::set($config, $key, $value);
        return $this->setConfig($config);
    }

    public function isUsingEmoteReactions(): bool {
        return Arr::get($this->getConfig() ?? [], 'features.emoteReactions', $this->type?->isInternal() ?? false);
    }

    public function setUsingEmoteReactions(bool $value): static {
        return $this->setConfigKey('features.emoteReactions', $value);
    }
}
