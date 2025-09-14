<?php

namespace App\Entity;

use App\Repository\ForumGroupRepository;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumGroupRepository::class)]
class ForumGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?array $titles = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $lang = null;

    #[ORM\Column(nullable: true)]
    private ?array $options = null;

    #[ORM\Column]
    private int $sort = 0;

    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column(nullable: true)]
    private ?array $overrides = null;

    /**
     * @var Collection<int, Forum>
     */
    #[ORM\OneToMany(targetEntity: Forum::class, mappedBy: 'forumGroup')]
    private Collection $forums;

    public function __construct()
    {
        $this->forums = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitles(): ?array
    {
        return $this->titles;
    }

    public function setTitles(?array $titles): static
    {
        $this->titles = $titles;

        return $this;
    }

    public function getLocalizedTitle(string $lang): string {
        return Arr::get($this->getTitles() ?? [], $lang) ?? $this->getTitle();
    }

    public function setLocalizedTitle(string $lang, ?string $title): static {
        $data = $this->getTitles() ?? [];
        Arr::set($data, $lang, $title);
        return $this->setTitles($data);
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): static
    {
        $this->lang = $lang;

        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function showIfSingleEntry(): bool {
        return Arr::get( $this->getOptions() ?? [], 'showIfSingleEntry', false );
    }

    public function setShowIfSingleEntry(bool $value): static {
        $options = $this->getOptions() ?? [];
        Arr::set( $options, 'showIfSingleEntry', $value );
        return $this->setOptions($options);
    }

    public function collapsedByDefault(): bool {
        return Arr::get( $this->getOptions() ?? [], 'collapsedByDefault', false );
    }

    public function setCollapsedByDefault(bool $value): static {
        $options = $this->getOptions() ?? [];
        Arr::set( $options, 'collapsedByDefault', $value );
        return $this->setOptions($options);
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getOverrides(): ?array
    {
        return $this->overrides;
    }

    public function setOverrides(?array $overrides): static
    {
        $this->overrides = $overrides;

        return $this;
    }

    public function getTitleOverride( Forum $forum, string $lang ): string {
        return
            Arr::get($this->overrides ?? [], "{$forum->getId()}.{$lang}.title") ??
            Arr::get($this->overrides ?? [], "{$forum->getId()}.default.title") ??
            $forum->getLocalizedTitle( $lang );
    }

    public function clearTitleOverride( Forum $forum, ?string $lang = 'default' ): static {
        $data = $this->getOverrides() ?? [];
        if ($lang === null) {
            unset($data[$forum->getId()]);
            return $this->setOverrides($data);
        } else return $this->setTitleOverride( $forum, null, $lang );
    }

    public function setTitleOverride( Forum $forum, ?string $title, string $lang = 'default' ): static {
        $data = $this->getOverrides() ?? [];
        Arr::set($data, "{$forum->getId()}.{$lang}.title", $title );
        return $this->setOverrides( $data );
    }

    /**
     * @return Collection<int, Forum>
     */
    public function getForums(): Collection
    {
        return $this->forums;
    }

    public function addForum(Forum $forum): static
    {
        if (!$this->forums->contains($forum)) {
            $this->forums->add($forum);
            $forum->setForumGroup($this);
        }

        return $this;
    }

    public function removeForum(Forum $forum): static
    {
        if ($this->forums->removeElement($forum)) {
            // set the owning side to null (unless already changed)
            if ($forum->getForumGroup() === $this) {
                $forum->setForumGroup(null);
            }

            $this->clearTitleOverride( $forum, null );
        }

        return $this;
    }
}
