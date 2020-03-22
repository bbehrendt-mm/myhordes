<?php


namespace App\Service;

use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\CauseOfDeath;
use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenProfession;
use App\Entity\Item;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use App\Entity\TownLogEntry;
use App\Entity\Zone;
use DateTime;
use DateTimeInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogTemplateHandler
{
    private $trans;
    private $asset;

    public function __construct(TranslatorInterface $t, Packages $a )
    {
        $this->trans = $t;
        $this->asset = $a;
    }

    private function wrap(string $obj): string {
        return "<span>$obj</span>";
    }

    /**
     * @param Item|ItemPrototype|ItemGroupEntry|Citizen|CitizenProfession|Building|BuildingPrototype|CauseOfDeath|CitizenHome|CitizenHomePrototype $obj
     * @param bool $small
     * @return string
     */
    private function iconize($obj, bool $small = false): string {
        if ($obj instanceof Item)        return $this->iconize( $obj->getPrototype(), $small );
        if ($obj instanceof Building)    return $this->iconize( $obj->getPrototype(), $small );
        if ($obj instanceof CitizenHome) return $this->iconize( $obj->getPrototype(), $small );

        if ($small) {
            if ($obj instanceof CitizenProfession) return "<img alt='' src='{$this->asset->getUrl( "build/images/professions/{$obj->getIcon()}.gif" )}' />";
        }

        if ($obj instanceof ItemPrototype)        return "<img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$obj->getIcon()}.gif" )}' /> {$this->trans->trans($obj->getLabel(), [], 'items')}";
        if ($obj instanceof ItemGroupEntry)       return "<img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$obj->getPrototype()->getIcon()}.gif" )}' /> {$this->trans->trans($obj->getPrototype()->getLabel(), [], 'items')} <i>x {$obj->getChance()}</i>";
        if ($obj instanceof BuildingPrototype)    return "<img alt='' src='{$this->asset->getUrl( "build/images/building/{$obj->getIcon()}.gif" )}' /> {$this->trans->trans($obj->getLabel(), [], 'buildings')}";
        if ($obj instanceof Citizen)              return $obj->getUser()->getUsername();
        if ($obj instanceof CitizenProfession)    return "<img alt='' src='{$this->asset->getUrl( "build/images/professions/{$obj->getIcon()}.gif" )}' /> {$this->trans->trans($obj->getLabel(), [], 'game')}";
        if ($obj instanceof CitizenHomePrototype) return "<img alt='' src='{$this->asset->getUrl( "build/images/home/{$obj->getIcon()}.gif" )}' /> {$this->trans->trans($obj->getLabel(), [], 'buildings')}";
        if ($obj instanceof CauseOfDeath)         return $this->trans->trans($obj->getLabel(), [], 'game');
        return "";
    }

    public function bankItemLog( Citizen $citizen, Item $item, bool $toBank ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeBank )
            ->setClass( $toBank ? TownLogEntry::ClassNone : TownLogEntry::ClassWarning )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans(
                $toBank
                    ? '%citizen% hat der Stadt folgendes gespendet: %item%'
                    : '%citizen% hat folgenden Gegenstand aus der Bank genommen: %item%', [
                        '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                        '%item%'    => $this->wrap( $this->iconize( $item ) ),
            ], 'game' ) );
    }

    public function wellLog( Citizen $citizen, bool $tooMuch ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeWell )
            ->setClass( $tooMuch ? TownLogEntry::ClassWarning : TownLogEntry::ClassNone )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans(
                $tooMuch
                    ? '%citizen% hat mehr Wasser genommen, als erlaubt ist...'
                    : '%citizen% hat eine Ration Wasser genommen.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
            ], 'game' ) );
    }

    public function wellAdd( Citizen $citizen, Item $item, int $count ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeWell )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('%citizen% hat %item% in den Brunnen geschüttet und damit %num% Rationen Wasser hinzugefügt.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%item%'    => $this->wrap( $this->iconize( $item ) ),
                '%num%'     => $this->wrap( "$count" ),
            ], 'game' ) );
    }

    public function constructionsInvestAP( Citizen $citizen, BuildingPrototype $proto, int $ap ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeConstruction )
            ->setClass( TownLogEntry::ClassNone )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans( '%citizen% hat an dem Bauprojekt %plan% mitgearbeitet und dabei %ap% ausgegeben.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%plan%'    => $this->wrap( $this->iconize( $proto ) ),
                '%ap%'      => "<div class='ap'>{$ap}</div>"
            ], 'game' ) );
    }

    public function constructionsNewSite( Citizen $citizen, BuildingPrototype $proto ): TownLogEntry {
        $str = '%citizen% hat einen Bauplan für %plan% gefunden.';
        if ($proto->getParent()) $str .= ' Dafür ist das Bauprojekt %parent% nötig.';
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeConstruction )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans( $str, [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%plan%'    => $this->wrap( $this->iconize( $proto ) ),
                '%parent%'  => $proto->getParent() ? $this->wrap( $this->iconize( $proto->getParent() ) ) : '???',
            ], 'game' ) );
    }

    public function constructionsBuildingComplete( Citizen $citizen, BuildingPrototype $proto ): TownLogEntry {
        $list = $proto->getResources() ? $proto->getResources()->getEntries()->getValues() : [];
        $list = array_map( function(ItemGroupEntry $e) { return $this->wrap( $this->iconize( $e ) ); }, $list );

        $str = 'Es wurde ein neues Gebäude gebaut: %plan%.';
        if (!empty($list)) $str .= ' Der Bau hat folgende Ressourcen verbraucht: %list%';

        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeConstruction )
            ->setSecondaryType( TownLogEntry::TypeBank )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setText( $this->trans->trans( $str, [
                '%plan%' => $this->wrap( $this->iconize( $proto ) ),
                '%list%' => implode( ', ', $list )
            ], 'game' ) );
    }

    public function doorControl( Citizen $citizen, bool $open ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeDoor )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('%citizen% hat das Stadttor %action%.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%action%'  => $this->wrap( $open ? $this->trans->trans('geöffnet', [], 'game') : $this->trans->trans('geschlossen', [], 'game') ),
            ], 'game' ) );
    }

    public function doorPass( Citizen $citizen, bool $in ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeDoor )
            ->setClass( TownLogEntry::ClassNone )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('%citizen% hat die Stadt %action%.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%action%'  => $this->wrap( $in ? $this->trans->trans('betreten', [], 'game') : $this->trans->trans('verlassen', [], 'game') ),
            ], 'game' ) );
    }

    public function citizenJoin( Citizen $citizen ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeCitizens )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('Ein neuer Bürger ist in der Stadt angekommen: %citizen%.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
            ], 'game' ) );
    }

    public function citizenProfession( Citizen $citizen ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeCitizens )
            ->setClass( TownLogEntry::ClassNone )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('%citizen% hat seine neue Berufung als %profession% gefunden.', [
                '%citizen%'    => $this->wrap( $this->iconize( $citizen ) ),
                '%profession%' => $this->wrap( $this->iconize( $citizen->getProfession() ) ),
            ], 'game' ) );
    }

    public function citizenDeath( Citizen $citizen, int $zombies = 0 ): TownLogEntry {
        switch ($citizen->getCauseOfDeath()->getRef()) {
            case CauseOfDeath::NightlyAttack:
                $str = '%citizen% wurde von %zombies% zerfleischt!';
                break;
            case CauseOfDeath::Vanished:
                $str = 'Hat jemand in letzter Zeit von %citizen% gehört? Er scheint verschwunden zu sein ...';
                break;
            case CauseOfDeath::Cyanide:
                $str = '%citizen% hat dem Druck nicht länger standgehalten und sein Leben beendet: %cod%.';
                break;
            case CauseOfDeath::Posion: case CauseOfDeath::GhulEaten:
                $str = 'Verrat! %citizen% ist gestorben: %cod%.';
                break;
            case CauseOfDeath::Hanging: case CauseOfDeath::FleshCage:
                $str = '%citizen% hat das Fass zum Überlaufen gebracht. Die Stadt hat seinen Tod entschieden: %cod%.';
                break;
            default: $str = '%citizen% hat seinen letzten Atemzug getan: %cod%!';
        }

        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeCitizens )
            ->setClass( TownLogEntry::ClassCritical )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans($str, [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%zombies%' => $this->wrap( $this->trans->trans( '%num% Zombies', ['%num%' => $zombies], 'game' ) ),
                '%cod%'     => $this->wrap( $this->iconize( $citizen->getCauseOfDeath() ) ),
            ], 'game' ) );
    }

    public function homeUpgrade( Citizen $citizen ): TownLogEntry {
        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeHome )
            ->setClass( TownLogEntry::ClassNone )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('%citizen% hat seine Behausung in ein(e,n) %home% verwandelt...', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%home%'    => $this->wrap( $this->iconize( $citizen->getHome() ) ),
            ], 'game' ) );
    }

    public function workshopConvert( Citizen $citizen, array $items_in, array $items_out ): TownLogEntry {
        $list1 = array_map( function($e) { return $this->wrap( $this->iconize( $e ) ); }, $items_in  );
        $list2 = array_map( function($e) { return $this->wrap( $this->iconize( $e ) ); }, $items_out );

        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeWorkshop )
            ->setSecondaryType( TownLogEntry::TypeBank )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans('%citizen% folgendes hergestellt: %list2%; dabei wurden folgende Gegenstände verbraucht: %list1%.', [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%list1%' => implode( ', ', $list1 ),
                '%list2%' => implode( ', ', $list2 )
            ], 'game' ) );
    }

    public function outsideMove( Citizen $citizen, Zone $zone1, Zone $zone2, bool $depart ): TownLogEntry {
        $is_zero_zone = ($zone1->getX() === 0 && $zone1->getY() === 0);

        $d_north = $zone2->getY() < $zone1->getY();
        $d_south = $zone2->getY() > $zone1->getY();
        $d_east  = $zone2->getX() > $zone1->getX();
        $d_west  = $zone2->getX() < $zone1->getX();

        $str = 'Horizont';
        if ($d_north) {
            if ($d_east)     $str = 'Nordosten';
            elseif ($d_west) $str = 'Nordwesten';
            else             $str = 'Norden';
        } elseif ($d_south) {
            if ($d_east)     $str = 'Südosten';
            elseif ($d_west) $str = 'Südwesten';
            else             $str = 'Süden';
        } elseif ($d_east)   $str = 'Osten';
        elseif ($d_west)     $str = 'Westen';

        if ($is_zero_zone)
            $base = $depart ?  '%citizen% hat das Stadtgebiet in Richtung %direction% verlassen.' : '%citizen% hat das Stadtgebiet aus Richtung %direction% betreten.';
        else $base = $depart ? '%citizen% (%profession%) ist Richtung %direction% aufgebrochen.' : '%citizen% (%profession%) ist aus dem %direction% angekommen.';

        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeVarious )
            ->setSecondaryType( $is_zero_zone ? TownLogEntry::TypeDoor : null )
            ->setClass( TownLogEntry::ClassNone )
            ->setTown( $citizen->getTown() )
            ->setZone( $is_zero_zone ? null : $zone1 )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( new DateTime('now') )
            ->setCitizen( $citizen )
            ->setText( $this->trans->trans($base, [
                '%citizen%'   => $this->wrap( $this->iconize( $citizen ) ),
                '%direction%' => $this->wrap( $this->trans->trans($str, [], 'game') ),
                '%profession%' => $this->wrap( $this->iconize( $citizen->getProfession(), true ) ),
            ], 'game' ) );
    }

    public function outsideDig( Citizen $citizen, ?ItemPrototype $item, ?DateTimeInterface $time = null ): TownLogEntry {
        $str = $item === null ? '%citizen% hat hier durch Graben nichts gefunden...' : '%citizen% hat ein(e,n) %item% ausgegraben!';

        return (new TownLogEntry())
            ->setType( TownLogEntry::TypeVarious )
            ->setClass( TownLogEntry::ClassInfo )
            ->setTown( $citizen->getTown() )
            ->setDay( $citizen->getTown()->getDay() )
            ->setTimestamp( $time ?? new DateTime('now') )
            ->setCitizen( $citizen )
            ->setZone( $citizen->getZone() )
            ->setText( $this->trans->trans($str, [
                '%citizen%' => $this->wrap( $this->iconize( $citizen ) ),
                '%item%' => $item ? $this->wrap( $this->iconize( $item ) ) : ''
            ], 'game' ) );
    }
}