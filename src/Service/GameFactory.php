<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use Doctrine\ORM\EntityManagerInterface;

class GameFactory
{
    private $entity_manager;
    private $validator;
    private $locksmith;
    private $item_factory;
    private $status_factory;

    const ErrorNone = 0;
    const ErrorTownClosed          = ErrorHelper::BaseTownSelectionErrors + 1;
    const ErrorUserAlreadyInGame   = ErrorHelper::BaseTownSelectionErrors + 2;
    const ErrorUserAlreadyInTown   = ErrorHelper::BaseTownSelectionErrors + 3;
    const ErrorNoDefaultProfession = ErrorHelper::BaseTownSelectionErrors + 4;

    public function __construct( EntityManagerInterface $em, GameValidator $v, Locksmith $l, ItemFactory $if, StatusFactory $sf)
    {
        $this->entity_manager = $em;
        $this->validator = $v;
        $this->locksmith = $l;
        $this->item_factory = $if;
        $this->status_factory = $sf;
    }

    private static $town_name_snippets = [
        [
            ['Tödliches','Modriges','Schimmliges','Eisiges','Rotes','Einsames','Ghulverseuchtes','Zombifiziertes','Bekanntes','Abgenagtes','Verstörendes','Letztes'],
            ['Wasserloch','Hospital','Trainingslager','Pony','Niemandsland','Gericht','Reich','Dreckloch','Gehirn','Rattenloch','Gebiet','Lager'],
        ],
        [
            ['Eifriger','Weinender','Schimmliger','Alberner','Autoritärer','Einsamer','Triefender','Kontaminierter','Verschlafener','Masochistischer','Besoffener','Kontaminierter'],
            ['Müllberg','Seelenfänger','Monarch','Fels','Untergang','Wald','Folterkeller','Bezirk','Bunker','Tisch','Husten','Laster'],
        ],
        [
            ['Eifrige','Modrige','Glitschige','Eisige','Drogensüchtige','Gespenstische','Ghulverseuchte','Zombifizierte','Bewegte','Betrunkene','Virulente','Betroffene'],
            ['Metzger','Zombieforscher','Gestalten','Wächter','Todesgesänge','Schaffner','Soldaten','Zwillinge','Regionen','Oberfläche','Schmarotzer','Entwickler'],
        ],
        [
            ['Ghulgebeine','Gesänge','Schmerzen','Schreie','Räume','Meute','Ghetto','Bürger','Hinterlassenschaft','Revier','Folterkeller','Alkoholpanscher'],
            ['des Todes','der Verdammnis','ohne Zukunft','am Abgrund','der Verwirrten','ohne Ideen','der Versager','der Ghule','der Superhelden','der Mutlosen','der Fröhlichen','der Revolutionäre'],
        ],
    ];

    public function createTownName(): string {
        return implode(' ', array_map(function(array $list): string {
            return $list[ array_rand( $list ) ];
        }, static::$town_name_snippets[array_rand( static::$town_name_snippets )]));
    }

    public function createTown( ?string $name, int $population, string $type ): ?Town {
        if (!$this->validator->validateTownType($type) || !$this->validator->validateTownPopulation( $population, $type ))
            return null;

        $town = new Town();
        $town
            ->setType( $this->entity_manager->getRepository(TownClass::class)->findOneByName( $type ) )
            ->setPopulation( $population )
            ->setName( $name ?: $this->createTownName() )
            ->setBank( new Inventory() )
            ->setWell( $type === TownClass::HARD ? mt_rand( 60, 90 ) : mt_rand( 90, 180 ) );

        return $town;
    }

    public function createCitizen( Town &$town, User &$user, ?int &$error ): ?Citizen {
        $error = self::ErrorNone;
        $lock = $this->locksmith->waitForLock('join-town');

        $active_citizen = $this->entity_manager->getRepository(Citizen::class)->findActiveByUser( $user );
        if ($active_citizen !== null) {
            $error = self::ErrorUserAlreadyInGame;
            return null;
        }

        if (!$town->isOpen()) {
            $error = self::ErrorTownClosed;
            return null;
        }
        foreach ($town->getCitizens() as $existing_citizen)
            if ($existing_citizen->getUser()->getId() === $user->getId()) {
                $error = self::ErrorUserAlreadyInTown;
                return null;
            }

        $base_profession = $this->entity_manager->getRepository(CitizenProfession::class)->findDefault();
        if ($base_profession === null) {
            $error = self::ErrorNoDefaultProfession;
            return null;
        }

        $home = new CitizenHome();
        $home->setChest( $chest = new Inventory() );

        $citizen = new Citizen();
        $citizen->setUser( $user )
            ->setTown( $town )
            ->setProfession( $base_profession )
            ->setInventory( new Inventory() )
            ->setHome( $home )
            ->setWellCounter( new WellCounter() )
            ->addStatus( $this->status_factory->createStatus( 'clean' ) );

        $chest
            ->addItem( $this->item_factory->createItem( 'chest_citizen_#00' ) )
            ->addItem( $this->item_factory->createItem( 'food_bag_#00' ) );

        return $citizen;
    }
}