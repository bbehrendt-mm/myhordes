<?php


namespace App\Twig;


use App\Entity\Morph;
use App\Entity\ReactionSet;
use App\Traits\Entity\LinksMorph;
use ArrayHelpers\Arr;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class MorphExtensions extends AbstractExtension implements GlobalsInterface
{
	protected array $morphCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) { }

    public function getFilters(): array
    {
        return [
            new TwigFilter('reactionUUID', [$this, 'reactionUUID']),
        ];
    }

    public function getFunctions(): array
    {
        return [

        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    /**
     * @param LinksMorph $data
     * @param class-string<Morph> $morph
     * @return string|null
     * @noinspection PhpDocSignatureInspection
     */
    private function resolveMorphID(object $data, string $morph): int|string|null {
        $dataClass  = $this->entityManager->getClassMetadata($data::class)?->getName();
        if (!$dataClass) return null;

        $primary = $data->tryPrimaryKey();
        if (!$primary) return null;

        $key = "morph_{$dataClass}::{$primary}::{$morph}";
        if (Arr::has($this->morphCache, $key)) return Arr::get($this->morphCache, $key);

        return $this->morphCache[$key] = $this->entityManager
            ->getRepository($morph)->findOneBy([
                'modelType' => $dataClass,
                'modelID' => $primary,
            ])?->getId();
    }

    /**
     * @param LinksMorph $data
     * @return string|null
     * @noinspection PhpDocSignatureInspection
     */
    public function reactionUUID(object $data): string|null {
        return $this->resolveMorphID( $data, ReactionSet::class );
    }
}
