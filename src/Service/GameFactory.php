<?php


namespace App\Service;

use App\Entity\AccountRestriction;
use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\Forum;
use App\Entity\Gazette;
use App\Entity\Inventory;
use App\Entity\MayorMark;
use App\Entity\Season;
use App\Entity\Thread;
use App\Entity\ThreadTag;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\TownRankingProxy;
use App\Entity\TownSlotReservation;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\Configuration\TownSetting;
use App\Service\Actions\Game\GenerateTownNameAction;
use App\Service\Actions\Game\InitializeTownBuildingsAction;
use App\Service\Maps\MapMaker;
use App\Structures\TownConf;
use App\Structures\TownSetup;
use App\Traits\System\PrimeInfo;
use App\Translation\T;
use DateInterval;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GameFactory
{
    use PrimeInfo;

    const int ErrorNone = 0;
    const int ErrorTownClosed          = ErrorHelper::BaseTownSelectionErrors + 1;
    const int ErrorUserAlreadyInGame   = ErrorHelper::BaseTownSelectionErrors + 2;
    const int ErrorUserAlreadyInTown   = ErrorHelper::BaseTownSelectionErrors + 3;
    const int ErrorNoDefaultProfession = ErrorHelper::BaseTownSelectionErrors + 4;

    const int ErrorTownNoCoaRoom         = ErrorHelper::BaseTownSelectionErrors + 5;
    const int ErrorMemberBlocked         = ErrorHelper::BaseTownSelectionErrors + 6;
    const int ErrorNotOnWhitelist        = ErrorHelper::BaseTownSelectionErrors + 7;
    const int ErrorSoulPointsRequired    = ErrorHelper::BaseTownSelectionErrors + 8;
    const int ErrorLangAntiGrief         = ErrorHelper::BaseTownSelectionErrors + 9;

    public function __construct(
        private readonly ConfMaster $conf,
        private readonly EntityManagerInterface $entity_manager,
        private readonly GameValidator $validator,
        private readonly Locksmith $locksmith,
        private readonly ItemFactory $item_factory,
        private readonly TownHandler $town_handler,
        private readonly TimeKeeperService $timeKeeper,
        private readonly RandomGenerator $random_generator,
        private readonly InventoryHandler $inventory_handler,
        private readonly CitizenHandler $citizen_handler,
        private readonly ZoneHandler $zone_handler,
        private readonly LogTemplateHandler $log,
        private readonly TranslatorInterface $translator,
        private readonly MapMaker $map_maker,
        private readonly CrowService $crow,
        private readonly UserHandler $user_handler,
        private readonly GameProfilerService $gps,
        private readonly EventProxyService $events,
        private readonly InitializeTownBuildingsAction $initializeTownBuildingsAction,
        private readonly GenerateTownNameAction $townNameAction,
    ) {}

    public function createTown( TownSetup $townSetup ): ?Town {
        if (!$this->validator->validateTownType($townSetup->type))
            return null;

        if ($townSetup->seeds) mt_srand($townSetup->seed);

        $townClass = $this->entity_manager->getRepository(TownClass::class)->findOneBy([ 'name' => $townSetup->type ]);

        // Initial: Create town
        $town = new Town();
        $town
            ->setType($townClass)
            ->setConf($townSetup->customConf);

        if ($townSetup->derives)
            $town->setDeriveConfigFrom( $townSetup->typeDeriveFrom );

        $currentSeason = $this->entity_manager->getRepository(Season::class)->findOneBy(['current' => true]);

        $town
            ->setSeason($currentSeason)
            ->setPrime( self::buildPrimePackageVersionIdentifier() );

        $conf = $this->conf->getTownConfiguration($town);

        if ($townSetup->population === null) $townSetup->population = mt_rand( $conf->get(TownSetting::PopulationMin), $conf->get(TownSetting::PopulationMax) );
        if ($townSetup->population <= 0 || $townSetup->population < $conf->get(TownSetting::PopulationMin) || $townSetup->population > $conf->get(TownSetting::PopulationMax))
            return null;

        $this->translator->setLocale($townSetup->language ?? 'de');

        $schema = null;
        $town
            ->setPopulation( $townSetup->population )
            ->setName( $townSetup->name ?: ($this->townNameAction)($townSetup->nameLanguage, $schema, $townSetup->nameMutator ) )
            ->setNameSchema( $schema )
            ->setLanguage( $townSetup->language )
            ->setBank( new Inventory() )
            ->setWell( mt_rand( $conf->get(TownSetting::DefaultWellFillMin), $conf->get(TownSetting::DefaultWellFillMax) ) );

        if ($bb_override = $this->conf->getGlobalConf()->getBlackboardOverrideFor( $townSetup->language ))
            $town->setWordsOfHeroes( $bb_override );

        ($this->initializeTownBuildingsAction)($town, $conf, true);

        $this->town_handler->calculate_zombie_attacks( $town, 3 );

        $this->map_maker->createMap( $town );

        $town->setForum((new Forum())->setTitle($town->getName()));
        foreach ($this->entity_manager->getRepository(ThreadTag::class)->findBy(['name' => ['help','rp','event','dsc_disc','dsc_guide','dsc_orga','dsc_game','dsc_flood']]) as $tag)
            $town->getForum()->addAllowedTag($tag);

        $create_qa_post = $conf->get(TownSetting::CreateQAPost);

        $this->crow->postToForum( $town->getForum(),
            [
                T::__('In diesem Thread dreht sich alles um die Bank.', 'game'),
                T::__('In diesem Thread dreht sich alles um die geplanten Verbesserungen des Tages.', 'game'),
                T::__('In diesem Thread dreht sich alles um die Werkstatt und um Ressourcen.', 'game'),
                T::__('In diesem Thread dreht sich alles um zukünftige Bauprojekte.', 'game'),
                ...($create_qa_post ? [
                    T::__('In diesem Thread können Fragen zum Leben in der Stadt gestellt werden.', 'game'),
                ] : []),
            ],
            true, true,
            [
                T::__('Bank', 'game'),
                T::__('Verbesserung des Tages', 'game'),
                T::__('Werkstatt', 'game'),
                T::__('Konstruktionen', 'game'),
                ...($create_qa_post ? [
                        T::__('Fragen & Antworten', 'game'),
                    ] : []),
                ],
            [
                Thread::SEMANTIC_BANK,
                Thread::SEMANTIC_DAILYVOTE,
                Thread::SEMANTIC_WORKSHOP,
                Thread::SEMANTIC_CONSTRUCTIONS,
                ...($create_qa_post ? [
                    Thread::SEMANTIC_QA,
                ] : []),
            ]
        );

        /** @var Gazette $gazette */
        $gazette = new Gazette();
        $gazette->setTown($town)->setDay($town->getDay());
        $town->addGazette($gazette);
        $this->entity_manager->persist($gazette);

        return $town;
    }

    public function userCanEnterTown( Town $town, User $user, bool $whitelist_enabled = false, ?int &$error = null, bool $internal = false ): bool {
        if (!$town->isOpen() || $town->getScheduledFor() > (new \DateTime())) {
            $error = self::ErrorTownClosed;
            return false;
        }

        if (!$internal && $this->user_handler->getConsecutiveDeathLock($user)) {
            $error = ErrorHelper::ErrorPermissionError;
            return false;
        }

        if (!$internal && $town->isMayor() && $town->getCreator()?->getId() !== $user->getId()) {
            $mark = !$this->entity_manager->getRepository(MayorMark::class)->matching( (new Criteria())
                ->where( new Comparison( 'user', Comparison::EQ, $user )  )
                ->andWhere( new Comparison( 'expires', Comparison::GT, new \DateTime() ) )
            )->isEmpty();

            if ($mark) {
                $error = ErrorHelper::ErrorPermissionError;
                return false;
            }
        }

        if (!$internal && $this->user_handler->isRestricted($user, AccountRestriction::RestrictionGameplay )) {
            $error = ErrorHelper::ErrorPermissionError;
            return false;
        }

        // Prevent lang restricted player from joining a different lang
        if (!$internal
            && $this->user_handler->isRestricted($user, AccountRestriction::RestrictionGameplayLang)
            && !$this->conf->getTownConfiguration( $town )->get( TownSetting::OptFeatureNoTeams )
            && !$town->getRankingEntry()?->getEvent()
            && $town->getLanguage() !== 'multi'
            && $town->getLanguage() !== $user->getTeam())
        {
            // $cap = $conf->get(MyHordesSetting::AntiGriefForeignCap);
            // if ($cap >= 0 && $cap <= $user->getTeamTicketsFor( $town->getSeason(), '!' )->count())
            $error = ErrorHelper::ErrorPermissionError;
            return false;
        }

        $conf = $this->conf->getGlobalConf();

        if (!$internal && !$this->conf->getTownConfiguration( $town )->get( TownSetting::OptFeatureNoSpRequired )) {

            $sp = $this->user_handler->fetchSoulPoints($user);
            $allowed = false;
            switch ($town->getType()->getName()) {
                case 'small':
                    $allowed = ($sp < $conf->get( MyHordesSetting::SoulPointRequirementRemote ) || $sp >= $conf->get( MyHordesSetting::SoulPointRequirementSmallReturn ));
                    break;
                case 'remote':
                    $allowed = $sp >= $conf->get( MyHordesSetting::SoulPointRequirementRemote );
                    break;
                case 'panda':
                    $allowed = $sp >= $conf->get( MyHordesSetting::SoulPointRequirementPanda );
                    break;
                case 'custom':
                    $allowed = $sp >= $conf->get( MyHordesSetting::SoulPointRequirementCustom );
                    break;
            }

            if (!$allowed && !$this->user_handler->checkFeatureUnlock( $user, 'f_sptkt', true )) {
                $error = self::ErrorSoulPointsRequired;
                return false;
            }
        }

        $whitelist = $whitelist_enabled ? $this->entity_manager->getRepository(TownSlotReservation::class)->findOneBy(['town' => $town, 'user' => $user]) : null;
        if ($whitelist_enabled && $whitelist === null && $user !== $town->getCreator()) {
            $error = self::ErrorNotOnWhitelist;
            return false;
        }

        if ($this->entity_manager->getRepository(Citizen::class)->findActiveByUser( $user ) !== null) {
            $error = self::ErrorUserAlreadyInGame;
            return false;
        }

        foreach ($town->getCitizens() as $existing_citizen)
            if ($existing_citizen->getUser() === $user) {
                $error = self::ErrorUserAlreadyInTown;
                return false;
            }

        return true;
    }

    public function createCitizen( Town &$town, User &$user, ?int &$error, ?array &$all_citizens = null, bool $internal = false ): ?Citizen {
        $error = self::ErrorNone;
        $lock = $this->locksmith->waitForLock('join-town');

        $whitelist_enabled = $this->entity_manager->getRepository(TownSlotReservation::class)->count(['town' => $town]) > 0;

        $followers = ($internal || $town->getPassword() || $whitelist_enabled) ? [] : $this->user_handler->getAvailableCoalitionMembers( $user );

        if (!$this->userCanEnterTown($town,$user, $whitelist_enabled,$error,$internal))
            return null;

        $followers = array_filter($followers, function (User $follower) use ($town,$whitelist_enabled,$internal): bool {
            return $this->userCanEnterTown($town,$follower,$whitelist_enabled,$e, $internal);
        });

        if (($town->getCitizenCount() + count($followers) + 1) > $town->getPopulation()) {
            $error = self::ErrorTownNoCoaRoom;
            return null;
        }

        $followers[] = $user;
        $main_citizen = null;
        $all_citizens = [];

        $before_event_cache = array_map(
            fn(User $joining_user) => $this->events->beforeTownJoinEvent( $town, $joining_user, $joining_user !== $user ),
            $followers
        );

        foreach ($followers as $joining_user) {
            $all_citizens[] = $citizen = $this->events->townJoinEvent( $town, $joining_user, $joining_user !== $user );
            if ($joining_user === $user) $main_citizen = $citizen;
        }

        $this->entity_manager->flush();

        foreach ($before_event_cache as $before_event) {
            $this->events->afterTownJoinEvent($town, $before_event);
            $this->entity_manager->flush();
        }

        $whitelist = $whitelist_enabled ? $this->entity_manager->getRepository(TownSlotReservation::class)->findOneBy(['town' => $town, 'user' => $user]) : null;
        if ($whitelist !== null) $this->entity_manager->remove($whitelist);

        $this->entity_manager->flush();

        return $main_citizen;
    }

    /**
     * @param Town|TownRankingProxy $town
     * @param bool $resetDay
     * @return void
     */
    public function updateTownScore(TownRankingProxy|Town $town, bool $resetDay = false): void {
        $score = $town->getBonusScore();
        $lastDay = 0;

        $tr = null;
        if (is_a( $town, Town::class )) $tr = $town->getRankingEntry();
        elseif (is_a( $town, TownRankingProxy::class )) $tr = $town;

        foreach ($tr->getCitizens() as $r_citizen) {
            /* @var CitizenRankingProxy $citizen */
            $score += $r_citizen->getDay();
            $lastDay = max( $lastDay, $r_citizen->getDay());
        }

        if ($resetDay && is_a( $town, Town::class )) $town->setDay( $lastDay );
        $this->entity_manager->persist( $tr->setDays($lastDay)->setScore($score) );
    }

    public function compactTown(Town $town): bool {

        foreach ($town->getCitizens() as $citizen) if ($citizen->getAlive()) return false;
        if ($town->isOpen() && !$town->getCitizens()->isEmpty()) return false;

        $this->updateTownScore($town, true);
        $this->gps->recordTownEnded($town);
        $this->entity_manager->remove($town);
        return true;
    }

    public function nullifyTown(Town $town, bool $force = false): bool {
        if ($town->isOpen() && !$force) return false;

        if ($town->getRankingEntry()) $this->entity_manager->remove($town->getRankingEntry());
        $this->entity_manager->remove($town);
        return true;
    }

    public function enableStranger(Town $town): bool {
        if (!$town->isOpen()) return false;

        $town->setStrangerPower( $town->getPopulation() - $town->getCitizenCount() );
        $town->setPopulation( $town->getCitizenCount() );
        $this->entity_manager->persist( $town );
        $this->entity_manager->persist( $this->log->strangerJoinProfession( $town, $this->timeKeeper->getCurrentAttackTime()->sub(DateInterval::createFromDateString('2min'))));
        return true;
    }
}
