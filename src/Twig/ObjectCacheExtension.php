<?php


namespace App\Twig;


use App\Entity\Item;
use App\Entity\ItemPrototype;
use App\Entity\User;
use App\Service\EventProxyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class ObjectCacheExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly EventProxyService $events
    ) { }

    public function getFilters(): array
    {
        return [
            new TwigFilter('itemCacheKey', [$this, 'get_item_cache_key']),
        ];
    }

    public function getFunctions(): array
    {
        return [];
    }

    public function getGlobals(): array
    {
        return [];
    }

    public function get_item_cache_key(Item|ItemPrototype $item, int $count = 1, bool $devMode = false): string {
        if (is_a($item, ItemPrototype::class)) return "item_prototype_{$item->getId()}_plain";
        else {
            $key = "item_prototype_{$item->getPrototype()->getId()}_instance_$count";
            if ($devMode) $key .= "_i{$item->getId()}";
            if ($item->getBroken()) $key .= "_b";
            if ($item->getEssential()) $key .= "_e";
            if ($item->getHidden()) $key .= "_h";
            if ($item->getFirstPick()) $key .= "_f";
            $key .= "_p{$item->getPoison()->value}";
            if ($item->getPrototype()->getWatchpoint() <> 0 && ($t = $item->getInventory()->findTown()))
                $key .= "_w{$this->events->buildingQueryNightwatchDefenseBonus( $t, $item )}";

            return $key;
        }
    }
}
