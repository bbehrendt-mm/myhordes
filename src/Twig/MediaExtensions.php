<?php


namespace App\Twig;


use App\Entity\Award;
use App\Entity\AwardPrototype;
use App\Entity\Citizen;
use App\Entity\Item;
use App\Entity\Media;
use App\Entity\Town;
use App\Entity\TownSlotReservation;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\User;
use App\Enum\UserSetting;
use App\Service\Actions\Game\DecodeConditionalMessageAction;
use App\Service\Actions\Ghost\ExplainTownConfigAction;
use App\Service\ConfMaster;
use App\Service\EventProxyService;
use App\Service\GameFactory;
use App\Service\LogTemplateHandler;
use App\Service\Media\MediaService;
use App\Service\PermissionHandler;
use App\Service\UserHandler;
use App\Structures\MyHordesConf;
use App\Traits\Entity\LinksMedia;
use ArrayHelpers\Arr;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MyHordes\Fixtures\DTO\Container;
use Normalizer;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class MediaExtensions extends AbstractExtension implements GlobalsInterface
{
	protected array $mediaCache = [];

    public function __construct(
        private readonly MediaService $mediaService,
        private readonly TagAwareCacheInterface $gameCachePool,
        private readonly EntityManagerInterface $entityManager,
    ) { }

    public function getFilters(): array
    {
        return [
            new TwigFilter('media', [$this, 'media']),
            new TwigFilter('singleMedia', [$this, 'singleMedia']),
            new TwigFilter('singleMediaSource', [$this, 'singleMediaSource']),
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
     * @throws Exception
     */
    private function cacheMedia(bool $single, object $data, string $collection): Media|Collection|null {
        $key = ($single ? 'single::' : '') . $data::class . '::' . $data->getId() . '::' . $collection;
        if (Arr::has($this->mediaCache, $key)) return Arr::get($this->mediaCache, $key);

        return $this->mediaCache[$key] = ($single
            ? $this->mediaService->getSingleMediaForObject($data, $collection)
            : $this->mediaService->getMediaForObject($data, $collection)
        );
    }

    /**
     * @return Collection<Media>
     * @throws Exception
     */
    public function media(object $data, string $collection) : Collection {
        return $this->cacheMedia(false, $data, $collection);
    }

    /**
     * @param object $data
     * @param string $collection
     * @return ?Media
     * @throws Exception
     */
    public function singleMedia(object $data, string $collection) : ?Media {
        return $this->cacheMedia(true, $data, $collection);
    }

    /**
     * @param ?object $data
     * @param string $collection
     * @param int|null $expected_size
     * @param bool $sourceSet
     * @return string
     * @throws InvalidArgumentException
     */
    public function singleMediaSource(?object $data, string $collection, ?int $expected_size = null, bool $sourceSet = false, string $fallback = '', bool $include_original = false) : string {
        if ($data === null) return '';

        $class = md5($this->entityManager->getClassMetadata($data::class)->getName());
        $identifier = $data->getPrimaryKey();

        $ex = explode('@', $collection);
        $collection = $ex[0];
        $tags = $ex[1] ?? 'default';

        $key = 'media_source_' .
            $class . '_' .
            $identifier . '_' .
            $collection . '_' .
            $tags . '_' .
            ($sourceSet ? 'set' : 'single') . '_' .
            ($expected_size === null ? 'max' : $expected_size);

        if ($include_original) $key .= '_with_original';

        $tags = explode('|', $tags);

        $result = $this->gameCachePool->get($key, function (ItemInterface $item) use ($sourceSet, $class, $identifier, $collection, $tags, $data, $expected_size, $fallback, $include_original) {
            $item->expiresAfter(604800)->tag([
                'media', "media_$class", "media_{$class}_{$identifier}",
                "media_{$class}_{$identifier}!{$collection}",
                "media_{$class}!{$collection}",
            ]);

            $media = $this->cacheMedia(true, $data, $collection);
            if (!$media) return '';

            foreach ($tags as $tag) {
                $result = match(true) {
                    $sourceSet && $expected_size !== null => $media->getSourceSetDPI( $expected_size, tag: $tag ),
                    $sourceSet => $media->getSourceSet($include_original, tag: $tag),
                    !$sourceSet => $media->getSource( $expected_size, $include_original, tag: $tag )
                };

                if (!empty($result)) return $result;
            }

            return '';
        });

        return (empty($result)) ? $fallback : $result;
    }
}
