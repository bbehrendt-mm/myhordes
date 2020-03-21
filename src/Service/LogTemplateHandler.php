<?php


namespace App\Service;

use App\Entity\Citizen;
use App\Entity\Item;
use App\Entity\TownLogEntry;
use DateTime;
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
     * @param Item|Citizen $obj
     * @return string
     */
    private function iconize($obj): string {
        if ($obj instanceof Item)    return "<img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$obj->getPrototype()->getIcon()}.gif" )}' /> {$this->trans->trans($obj->getPrototype()->getLabel(), [], 'items')}";
        if ($obj instanceof Citizen) return $obj->getUser()->getUsername();
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
            ] ) );
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
            ] ) );
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
            ] ) );
    }

}