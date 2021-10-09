<?php


namespace App\Twig;


use App\Entity\Award;
use App\Entity\Item;
use App\Entity\Town;
use App\Entity\TownSlotReservation;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\User;
use App\Service\CitizenHandler;
use App\Service\GameFactory;
use App\Service\TownHandler;
use App\Service\UserHandler;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extensions extends AbstractExtension  implements GlobalsInterface
{
    private TranslatorInterface $translator;
    private UrlGeneratorInterface $router;
    private UserHandler $userHandler;
    private EntityManagerInterface $entityManager;
    private CitizenHandler $citizenHandler;
    private TownHandler $townHandler;
    private GameFactory $gameFactory;

    public function __construct(TranslatorInterface $ti, UrlGeneratorInterface $r, UserHandler $uh, EntityManagerInterface $em, CitizenHandler $ch, TownHandler $th, GameFactory $gf) {
        $this->translator = $ti;
        $this->router = $r;
        $this->userHandler = $uh;
        $this->entityManager = $em;
        $this->citizenHandler = $ch;
        $this->townHandler = $th;
        $this->gameFactory = $gf;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('instance_of', [$this, 'instance_of']),
            new TwigFilter('to_date',  [$this, 'create_date']),
            new TwigFilter('is_granted',  [$this, 'check_granted']),
            new TwigFilter('has_unlocked',  [$this, 'check_unlocked']),
            new TwigFilter('bin_contains',  [$this, 'check_flag_1']),
            new TwigFilter('bin_overlaps',  [$this, 'check_flag_2']),
            new TwigFilter('restricted',  [$this, 'user_is_restricted']),
            new TwigFilter('restricted_until',  [$this, 'user_restricted_until']),
            new TwigFilter('whitelisted',  [$this, 'town_whitelisted']),
            new TwigFilter('openFor',  [$this, 'town_openFor']),
            new TwigFilter('items',  [$this, 'item_prototypes_with']),
            new TwigFilter('group_titles',  [$this, 'group_titles']),
            new TwigFilter('watchpoint',  [$this, 'fetch_watch_points']),
            new TwigFilter('related',  [$this, 'user_relation']),
            new TwigFilter('filesize',  [$this, 'format_filesize']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('instance_of', [$this, 'instance_of']),
            new TwigFunction('to_date',  [$this, 'create_date']),
            new TwigFunction('help_btn', [$this, 'help_btn'], ['is_safe' => array('html')]),
            new TwigFunction('help_lnk', [$this, 'help_lnk'], ['is_safe' => array('html')]),
            new TwigFunction('tooltip', [$this, 'tooltip'], ['is_safe' => array('html')]),
        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    public function instance_of($object, $classname): bool {
        return is_a($object, $classname);
    }

    /**
     * @param string $object
     * @return DateTime
     * @throws Exception
     */
    public function create_date(string $object): DateTime {
        return new DateTime($object);
    }

    /**
     * @param User $u
     * @param string $role
     * @return bool
     */
    public function check_granted(User $u, string $role): bool {
        return $this->userHandler->hasRole($u,$role);
    }

    /**
     * @param User $u
     * @param string $feature
     * @return bool
     */
    public function check_unlocked(User $u, string $feature): bool {
        return $this->userHandler->checkFeatureUnlock($u, $feature, false);
    }

    public function help_btn(string $tooltipContent): string {
        return "<a class='help-button'><div class='tooltip help'>$tooltipContent</div>" . $this->translator->trans("Hilfe", [], "global") . "</a>";
    }

    public function help_lnk(string $name, string $controller = null, array $args = []): string {
        $link = $controller !== null ? $this->router->generate($controller, $args) : "";
        return "<span class='helpLink'><span class='helptitle'>" . $this->translator->trans("Spielhilfe:", [], "global") . "</span> <a class='link' x-ajax-href='$link' target='_blank'>$name</a></span>";
    }

    public function tooltip(string $content, string $classes = null): string {
        return "<div class='tooltip $classes'>$content</div>";
    }

    public function check_flag_1(int $val, int $mask): bool {
        return ($val & $mask) === $mask;
    }

    public function check_flag_2(int $val, int $mask): bool {
        return $val & $mask;
    }

    public function user_is_restricted(User $user, ?int $mask = null): bool {
        return $this->userHandler->isRestricted($user,$mask);
    }

    public function user_relation(User $user, User $other, int $relation): bool {
        return $this->userHandler->checkRelation($user,$other,$relation);
    }

    public function format_filesize(int $size): string {
        if     ($size >= 1099511627776) return round($size/1099511627776, 0) . ' TB';
        elseif ($size >= 1073741824)    return round($size/1073741824, 1) . ' GB';
        elseif ($size >= 1048576)       return round($size/1048576, 2) . ' MB';
        elseif ($size >= 1024)          return round($size/1024, 0) . ' KB';
        else                            return $size . ' B';
    }

    public function town_whitelisted(Town $town, ?User $user = null): bool {
        return $user
            ? ($this->entityManager->getRepository(TownSlotReservation::class)->count(['town' => $town, 'user' => $user]) === 1)
            : ($this->entityManager->getRepository(TownSlotReservation::class)->count(['town' => $town]) > 0);
    }

    public function town_openFor(Town $town, User $user = null): bool {
        return $this->gameFactory->userCanEnterTown($town,$user,$this->entityManager->getRepository(TownSlotReservation::class)->count(['town' => $town]) > 0);
    }

    public function user_restricted_until(User $user, ?int $mask = null): ?DateTime {
        return $this->userHandler->getActiveRestrictionExpiration($user,$mask);
    }

    /**
     * @param string $tag
     * @return ItemPrototype[]
     */
    public function item_prototypes_with(string $tag): array {
        /** @var ItemProperty|null $p */
        $p = $this->entityManager->getRepository(ItemProperty::class)->findOneByName($tag);
        if ($p === null) return [];
        return $p->getItemPrototypes()->getValues();
    }

    /**
     * @param Award[] $awards
     * @return array
     */
    public function group_titles(array $awards): array {

        $g = [];
        $p = [];

        foreach ($awards as $award) {
            $id = 'custom';
            if ($award->getPrototype() !== null) {
                if ($award->getPrototype()->getAssociatedPicto() !== null) {
                    $p[$id = $award->getPrototype()->getAssociatedPicto()->getId()] = $award->getPrototype()->getAssociatedPicto();
                } else $id = 'single';
            }

            if (!isset($g[$id])) $g[$id] = [];
            $g[$id][] = $award;
        }

        uksort($g, function($a,$b) use (&$p) {
            if ($a === 'custom' && $b === 'custom') return 0;
            if ($a === 'custom') return -1;
            if ($b === 'custom') return 1;
            if ($a === 'single' && $b === 'single') return 0;
            if ($a === 'single') return -1;
            if ($b === 'single') return 1;
            return $p[$b]->getRare() <=> $p[$a]->getRare() ?: $p[$a]->getId() <=> $p[$b]->getId();
        });

        foreach ($g as &$list) usort( $list, function( Award $a, Award $b ) {
            if (!$a->getPrototype() && !$b->getPrototype()) return $a->getId() <=> $b->getId();
            else if (!$a->getPrototype()) return -1;
            else if (!$b->getPrototype()) return  1;
            else return $a->getPrototype()->getUnlockQuantity() <=> $b->getPrototype()->getUnlockQuantity();
        } );
        return $g;
    }

    /**
     * @param Item|ItemPrototype $item
     * @return int
     */
    public function fetch_watch_points($item): int {

        if ($this->instance_of($item, ItemPrototype::class)) return $item->getWatchpoint();
        if ($item->getInventory() === null) return $item->getPrototype()->getWatchpoint();

        $town = null;
        if ($item->getInventory()->getTown()) $town = $item->getInventory()->getTown();
        else if ($item->getInventory()->getCitizen()) $town = $item->getInventory()->getCitizen()->getTown();
        else if ($item->getInventory()->getZone()) $town = $item->getInventory()->getZone()->getTown();
        else if ($item->getInventory()->getHome()) $town = $item->getInventory()->getHome()->getCitizen()->getTown();
        else if ($item->getInventory()->getRuinZone()) $town = $item->getInventory()->getRuinZone()->getZone()->getTown();
        else if ($item->getInventory()->getRuinZoneRoom()) $town = $item->getInventory()->getRuinZoneRoom()->getZone()->getTown();

        if ($town === null) return $item->getPrototype()->getWatchpoint();
        return $this->citizenHandler->getNightWatchItemDefense($item,
            (bool)$this->townHandler->getBuilding($town, 'small_tourello_#00', true),
            (bool)$this->townHandler->getBuilding($town, 'small_catapult3_#00', true),
            (bool)$this->townHandler->getBuilding($town, 'small_ikea_#00', true),
            (bool)$this->townHandler->getBuilding($town, 'small_armor_#00', true)
        );
    }
}