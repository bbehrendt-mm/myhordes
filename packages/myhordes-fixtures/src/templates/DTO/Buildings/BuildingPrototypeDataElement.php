<?php

namespace MyHordes\Fixtures\DTO\Buildings;

use App\Entity\BuildingPrototype;
use App\Entity\ItemCategory;
use App\Entity\ItemGroup;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MyHordes\Fixtures\DTO\Element;
use MyHordes\Fixtures\DTO\LabeledIconElementInterface;

/**
 * @property string $label
 * @method self label(string $v)
 * @property string $icon
 * @method self icon(string $v)
 * @property ?string $parentBuilding
 * @method self parentBuilding(string $v)
 * @property string $description
 * @method self description(string $v)
 * @property bool $isTemporary
 * @method self isTemporary(bool $v)
 * @property bool $isImpervious
 * @method self isImpervious(bool $v)
 * @property int $orderBy
 * @method self orderBy(int $v)
 * @property int $defense
 * @method self defense(int $v)
 * @property int $health
 * @method self health(int $v)
 * @property int $ap
 * @method self ap(int $v)
 * @property int $hardAp
 * @method self hardAp(int $v)
 * @property int $easyAp
 * @method self easyAp(int $v)
 * @property int $blueprintLevel
 * @method self blueprintLevel(int $v)
 * @property array $resources
 * @method self resources(array $v)
 * @method self resource(string $key, int $value)
 * @property array $hardResources
 * @method self hardResources(array $v)
 * @method self hardResource(string $key, int $value)
 * @property array $easyResources
 * @method self easyResources(array $v)
 * @method self easyResource(string $key, int $value)
 * @property int $voteLevel
 * @method self voteLevel(int $v)
 * @property string $baseVoteText
 * @method self baseVoteText(string $v)
 * @property string[] $upgradeTexts
 * @method self upgradeTexts(array $v)
 * @property bool $hasHardMode
 * @method self hasHardMode(bool $v)
 * @method self adjustForHardMode(?int $ap, ?array $resources, int $easyAp = null, ?array $easyResources = null)
 * @method self autoEasyMode()
 *
 * @method BuildingPrototypeDataContainer commit(string &$id = null)
 * @method BuildingPrototypeDataContainer discard()
 */
class BuildingPrototypeDataElement extends Element implements LabeledIconElementInterface {

    private function createResourceGroup(EntityManagerInterface $em, array $resources, string $id, string $subkey): ItemGroup {
        $group = $em->getRepository(ItemGroup::class)->findOneByName("{$id}_{$subkey}") ?? (new ItemGroup())->setName( "{$id}_{$subkey}" );
        $group->getEntries()->clear();

        foreach ($resources as $item_name => $count) {
            if (!($item = $em->getRepository(ItemPrototype::class)->findOneBy( ['name' => $item_name] )))
                throw new Exception( "Item class not found: '$item_name' (when building resource list '{$id}_{$subkey}')." );

            $group->addEntry( (new ItemGroupEntry())->setPrototype( $item )->setChance( $count ) );
        }

        return $group;
    }

    /**
     * @throws Exception
     */
    public function toEntity(EntityManagerInterface $em, string $id, BuildingPrototype $entity): void {
        try {
            $entity
                ->setLabel( $this->label )
                ->setDescription( $this->description )
                ->setTemp( $this->isTemporary ?? false )
                ->setAp( $this->ap ?? 0 )
                ->setBlueprint( $this->blueprintLevel ?? 0 )
                ->setDefense( $this->defense ?? 0 )
                ->setIcon( $this->icon )
                ->setHp( $this->health ?: $this->ap ?: 0 )
                ->setImpervious( $this->isImpervious ?? false )
                ->setOrderBy( $this->orderBy ?? 0 )
                ->setResources( $this->resources ? $this->createResourceGroup($em, $this->resources, $id, 'rsc') : null )
                ->setHasHardMode( $this->hasHardMode ?? false )
                ->setHardAp( $this->hasHardMode ? $this->hardAp : null )
                ->setEasyAp( $this->hasHardMode ? $this->easyAp : null )
                ->setHardResources( ($this->hasHardMode && $this->hardResources) ? $this->createResourceGroup($em, $this->hardResources, $id, 'hrsc') : null )
                ->setEasyResources( ($this->hasHardMode && $this->easyResources) ? $this->createResourceGroup($em, $this->easyResources, $id, 'ersc') : null );

            if ($this->voteLevel > 0)
                $entity
                    ->setMaxLevel( $this->voteLevel )
                    ->setZeroLevelText( $this->baseVoteText ?? "")
                    ->setUpgradeTexts( array_slice( array_pad($this->upgradeTexts ?? [], $this->voteLevel, "???" ), 0, $this->voteLevel ) );
            else $entity->setMaxLevel( 0 )->setZeroLevelText( null )->setUpgradeTexts( null );

        } catch (\Throwable $t) {
            throw new Exception(
                "Exception when persisting building prototype to database: {$t->getMessage()} \n\nOccurred when processing the following item:\n" . print_r($this->toArray(), true),
                previous: $t
            );
        }


    }

    public function __call(string $name, array $arguments): Element
    {
        if ($name === 'resource' && count($arguments) === 2) {
            [$key, $value] = $arguments;
            $r = $this->resources ?? [];
            if ($value <= 0) unset( $r[$key] );
            else $r[$key] = $value;
            return $this->resources($r);
        } elseif ($name === 'hardResource' && count($arguments) === 2) {
            [$key, $value] = $arguments;
            $r = $this->hardResources ?? [];
            if ($value <= 0) unset( $r[$key] );
            else $r[$key] = $value;
            return $this->hardResources($r);
        } elseif ($name === 'easyResource' && count($arguments) === 2) {
            [$key, $value] = $arguments;
            $r = $this->easyResources ?? [];
            if ($value <= 0) unset( $r[$key] );
            else $r[$key] = $value;
            return $this->easyResources($r);
        } elseif ($name === 'adjustForHardMode' && count($arguments) === 2) {
            [$ap, $resources] = $arguments;
            $this->hasHardMode = true;
            $this->hardAp = $ap ?? $this->ap;
            $this->hardResources = $this->resources;
            foreach ($resources ?? [] as $k => $v) $this->hardResource($k, $v);
            return $this;
        } elseif ($name === 'adjustForHardMode' && count($arguments) === 4) {
            [$ap, $resources, $easyAp, $easyResources] = $arguments;
            $this->hasHardMode = true;
            $this->hardAp = $ap ?? $this->ap;
            $this->easyAp = $easyAp ?? $this->ap;
            $this->hardResources = $this->easyResources = $this->resources;
            foreach ($resources ?? [] as $k => $v) $this->hardResource($k, $v);
            foreach ($easyResources ?? [] as $k => $v) $this->hardResource($k, $v);
            return $this;
        } elseif ($name === 'autoEasyMode' && count($arguments) === 0) {
            $this->easyResources = $this->resources;
            $original = $this->resources;
            foreach ($this->hardResources ?? [] as $k => $v) {
                $ov = $original[$k] ?? 0;
                if ($ov > 0) $this->easyResource($k, round($v/(($v / $ov) + 1)));
            }
            return $this;
        } else return parent::__call($name, $arguments);
    }

}