<?php

namespace App\Service\Actions\Game;

use App\Entity\Citizen;
use App\Entity\HeroicActionPrototype;
use App\Entity\Requirement;
use App\Entity\Result;
use App\Enum\Configuration\CitizenProperties;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use MyHordes\Fixtures\DTO\Actions\EffectsDataContainer;
use MyHordes\Fixtures\DTO\Actions\RequirementsDataContainer;

readonly class HeroSkillUpgradeCheck
{
    public function __construct(
    ) { }


    public function __invoke(
        HeroicActionPrototype $skill,
        Citizen $reference,
        Citizen $owner,
    )
    {
        /** @var CitizenProperties[] $requirementProps */
        $requirementProps = $skill->getAction()->getRequirements()
            ->map( fn(Requirement $r) => (new RequirementsDataContainer())->fromArray([['atomList' => $r->getAtoms()]]) )
            ->map( fn(RequirementsDataContainer $r) => $r->injectedProperties( CitizenProperties::class ) )
            ->filter( fn(array $r) => !empty($r) )
            ->reduce( fn(array $carry, array $r) => array_merge($carry, $r), [] );

        /** @var CitizenProperties[] $effectProps */
        $effectProps = $skill->getAction()->getResults()
            ->map( fn(Result $r) => (new EffectsDataContainer())->fromArray([['atomList' => $r->getAtoms()]]) )
            ->map( fn(EffectsDataContainer $r) => $r->injectedProperties( CitizenProperties::class ) )
            ->filter( fn(array $r) => !empty($r) )
            ->reduce( fn(array $carry, array $r) => array_merge($carry, $r), [] );

        $applySort = function(int &$v, array $props) use ( $reference, $owner ) {
            $props = array_map(fn(string $v) => CitizenProperties::from($v), array_unique( array_map(fn(CitizenProperties $v) => $v->value, $props )));
            foreach ( $props as $prop ) {
                $v = match ( true ) {
                    $v === null => null,
                    $v === 0 => $prop->compare( $reference->property( $prop ), $owner->property( $prop ) ),
                    $v < 0   => ($prop->compare( $reference->property( $prop ), $owner->property( $prop ) ) <= 0) ? $v : null,
                    $v > 0   => ($prop->compare( $reference->property( $prop ), $owner->property( $prop ) ) >= 0) ? $v : null,
                };
            }
        };

        $requirementSort = 0;
        $applySort($requirementSort, $requirementProps);

        $effectSort = 0;
        $applySort($effectSort, $effectProps);

        return match ( true ) {
            is_null( $requirementSort) || is_null( $effectSort ) => 0,
            $requirementSort > 0 || $effectSort < 0 => -1,
            $requirementSort < 0 || $effectSort > 0 =>  1,
            default => 0
        };
    }
}