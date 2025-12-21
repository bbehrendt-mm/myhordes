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
use ArrayHelpers\Arr;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MyHordes\Fixtures\DTO\Container;
use Normalizer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    ) { }

    public function getFilters(): array
    {
        return [
            new TwigFilter('media', [$this, 'media']),
            new TwigFilter('singleMedia', [$this, 'singleMedia']),
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
}
