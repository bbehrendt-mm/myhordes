<?php


namespace App\Twig;


use App\Entity\Award;
use App\Entity\AwardPrototype;
use App\Entity\Item;
use App\Entity\Town;
use App\Entity\TownSlotReservation;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\User;
use App\Enum\UserSetting;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\GameFactory;
use App\Service\LogTemplateHandler;
use App\Service\TownHandler;
use App\Service\UserHandler;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function PHPUnit\Framework\matches;

class Extensions extends AbstractExtension implements GlobalsInterface
{
    private TranslatorInterface $translator;
    private UrlGeneratorInterface $router;
    private UserHandler $userHandler;
    private EntityManagerInterface $entityManager;
    private CitizenHandler $citizenHandler;
    private TownHandler $townHandler;
    private GameFactory $gameFactory;
    private EntrypointLookupInterface $entryPoints;
    private ConfMaster $conf;

    public function __construct(TranslatorInterface $ti, UrlGeneratorInterface $r, UserHandler $uh, EntityManagerInterface $em, CitizenHandler $ch, TownHandler $th, GameFactory $gf, EntrypointLookupInterface $epl, ConfMaster $c) {
        $this->translator = $ti;
        $this->router = $r;
        $this->userHandler = $uh;
        $this->entityManager = $em;
        $this->citizenHandler = $ch;
        $this->townHandler = $th;
        $this->gameFactory = $gf;
        $this->entryPoints = $epl;
        $this->conf = $c;

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
            new TwigFilter('conf',  [$this, 'town_conf']),
            new TwigFilter('items',  [$this, 'item_prototypes_with']),
            new TwigFilter('group_titles',  [$this, 'group_titles']),
            new TwigFilter('watchpoint',  [$this, 'fetch_watch_points']),
            new TwigFilter('related',  [$this, 'user_relation']),
            new TwigFilter('filesize',  [$this, 'format_filesize']),
            new TwigFilter('dogname',  [$this, 'dogname']),
            new TwigFilter('color',  [$this, 'color']),
            new TwigFilter('textcolor',  [$this, 'color_tx']),
            new TwigFilter('translated_title',  [$this, 'translatedTitle']),
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
            new TwigFunction('tooltip', [$this, 'tooltip'], ['is_safe' => array('html')]),
        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    public function instance_of($object, string $classname): bool {
        return $classname === 'object' ? is_object($object) : is_a($object, $classname);
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
     * @return string
     */
    public function dogname(User $u): string {
        return LogTemplateHandler::generateDogName( $u->getActiveCitizen() ? $u->getActiveCitizen()->getId() : 0, $this->translator );
    }

    /**
     * @param string $bin
     * @return string
     */
    public function color(string $bin): string {
        return '#' . bin2hex($bin);
    }

    /**
     * @param string $color
     * @return string
     */
    public function color_tx(string $color): string {
        if (empty($color)) return '#000';
        if (mb_substr($color,0,1) === '#') $color = mb_substr($color,1);

        switch (mb_strlen($color)) {
            case 3: case 4: $color = "{$color[0]}{$color[0]}{$color[1]}{$color[1]}{$color[2]}{$color[2]}"; break;
            case 6: case 8: $color = substr($color, 0, 6); break;
            default: $color = 'ffffff';
        }

        return (
            hexdec( substr($color, 0, 2) ) * .299 +
            hexdec( substr($color, 2, 2) ) * .587 +
            hexdec( substr($color, 4, 2) ) * .114
        ) > 150 ? '#000000' : '#ffffff';


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

    public function town_conf(Town $town, ?string $property = null, mixed $default = null): mixed {
        $c = $this->conf->getTownConfiguration( $town );
        return $property === null ? $c->raw() : $c->get($property,$default);
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
            return $p[$b]->getRare() <=> $p[$a]->getRare() ?: $p[$a]->getPriority() <=> $p[$b]->getPriority() ?: $p[$a]->getId() <=> $p[$b]->getId();
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

    public function translatedTitle(string|Award|AwardPrototype|User $subject, User $object, ?User $object2 = null): string {

        /** @var $owner User */
        /** @var $me User */
        [$base, $owner, $me] = match (true) {
            is_string( $subject ) => [$subject, $object, $object2],
            is_a( $subject, Award::class ) => [$subject->getPrototype()?->getTitle(), $object, $object2],
            is_a( $subject, AwardPrototype::class ) => [$subject->getTitle(), $object, $object2],
            is_a( $subject, User::class ) => [$subject->getActiveTitle()?->getPrototype()?->getTitle(), $subject, $object],
            default => [null,null,null]
        };

        if ($base === null) return '';
        $lang = match ($owner?->getSetting( UserSetting::TitleLanguage )) {
            null, '_them' => $me?->getLanguage(),
            '_me' => $owner?->getLanguage() ?? $me?->getLanguage(),
            'de', 'en', 'fr', 'es' => $owner?->getSetting( UserSetting::TitleLanguage ),
            default => null
        };

        return $this->translator->trans($base, [], 'game', $lang);
    }
}