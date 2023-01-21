<?php

namespace App\Translation;

use MyHordes\Plugins\Fixtures\Action;
use MyHordes\Plugins\Fixtures\AwardFeature;
use MyHordes\Plugins\Fixtures\AwardTitle;
use MyHordes\Plugins\Fixtures\Building;
use MyHordes\Plugins\Fixtures\CitizenComplaint;
use MyHordes\Plugins\Fixtures\CitizenDeath;
use MyHordes\Plugins\Fixtures\CitizenHomeLevel;
use MyHordes\Plugins\Fixtures\CitizenHomeUpgrade;
use MyHordes\Plugins\Fixtures\CitizenProfession;
use MyHordes\Plugins\Fixtures\CitizenRole;
use MyHordes\Plugins\Fixtures\CitizenStatus;
use MyHordes\Plugins\Fixtures\CouncilEntry;
use MyHordes\Plugins\Fixtures\ForumThreadTag;
use MyHordes\Plugins\Fixtures\GazetteEntry;
use MyHordes\Plugins\Fixtures\HeroSkill;
use MyHordes\Plugins\Fixtures\Item;
use MyHordes\Plugins\Fixtures\ItemCategory;
use MyHordes\Plugins\Fixtures\Log;
use MyHordes\Plugins\Fixtures\Picto;
use MyHordes\Plugins\Fixtures\Recipe;
use MyHordes\Plugins\Fixtures\Ruin;
use MyHordes\Plugins\Fixtures\Town;
use MyHordes\Plugins\Fixtures\ZoneTag;
use MyHordes\Plugins\Interfaces\FixtureChainInterface;
use MyHordes\Plugins\Interfaces\FixtureProcessorInterface;
use MyHordes\Plugins\Management\FixtureSourceLookup;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\Extractor\Visitor\AbstractVisitor;

final class FixtureVisitor extends AbstractVisitor implements NodeVisitor
{
    public function __construct(
        private readonly FixtureSourceLookup $lookup,
        private readonly ContainerInterface $container
    ) {}

    public function beforeTraverse(array $nodes): ?Node
    {
        return null;
    }

    protected function extractArrayData( array $data, string $domain ): bool {
        foreach ( array_filter($data) as $message )
            $this->addMessageToCatalogue($message, $domain, 0);
        return true;
    }

    protected function extractColumnData( array $data, array|string $columns, string $domain ): bool {
        return array_reduce(
            array_map(
                fn($column) => $this->extractArrayData( array_column( $data, $column ), $domain ),
                is_array($columns) ? $columns : [$columns]
            ),
            fn($c,$r) => $r && $c,
            true
        );
    }

    protected function flattenBuildingData( array $data ): array {
        do {
            $children = array_reduce( array_column( $data, 'children' ),
                fn(array $c, array $a) => array_merge( $c, $a ), []
            );

            $data = array_merge( array_map( function($entry) {
                unset( $entry['children'] );
                return $entry;
            }, $data ), $children );
        } while (!empty($children));

        return $data;
    }

    protected function extractData( FixtureChainInterface $provider, array $data ): bool {
        if ($provider::class === Building::class)
            $data = $this->flattenBuildingData( $data );

        return match ($provider::class) {
            Action::class =>
                $this->extractArrayData( $data['message_keys'] ?? [], 'items') &&
                $this->extractColumnData( $data['meta_requirements'] ?? [], 'text', 'items') &&
                $this->extractColumnData( $data['actions'] ?? [], ['label','tooltip','confirmMsg','message','escort_message'], 'items') &&
                $this->extractColumnData( $data['escort'] ?? [], ['label','tooltip'], 'items') &&
                $this->extractColumnData( array_column( $data['meta_results'] ?? [], 'message' ), 'text', 'items'),
            AwardTitle::class => $this->extractColumnData($data, 'title', 'game'),
            Item::class =>
                $this->extractArrayData( $data['descriptions'] ?? [], 'items') &&
                $this->extractColumnData( $data['items'] ?? [], 'label', 'items'),
            Recipe::class => $this->extractColumnData( $data, ['action','tooltip'], 'items'),
            ItemCategory::class => $this->extractColumnData( $data, 'label', 'items'),
            AwardFeature::class => $this->extractColumnData( $data, ['label', 'desc'], 'items'),
            Building::class =>
                $this->extractColumnData( $data, ['name', 'desc','lv0text'], 'buildings') &&
                $this->extractArrayData( array_column( $data, 'upgradeTexts' ), 'buildings'),
            CitizenHomeLevel::class => $this->extractColumnData( $data, 'label', 'buildings'),
            CitizenHomeUpgrade::class => $this->extractColumnData( $data, ['label','desc'], 'buildings'),
            CitizenStatus::class,
            Picto::class => $this->extractColumnData( $data, ['label','description'], 'game'),
            CitizenDeath::class,
            CitizenProfession::class => $this->extractColumnData( $data, ['label','desc'], 'game'),
            CitizenRole::class => $this->extractColumnData( $data, ['label','message'], 'game'),
            Ruin::class => $this->extractColumnData( $data, ['label','desc','explorable_desc'], 'game'),
            ZoneTag::class => $this->extractColumnData( $data, 'label', 'game'),
            Town::class => $this->extractColumnData( $data, ['label', 'help'], 'game'),
            Log::class,
            CitizenComplaint::class => $this->extractColumnData( $data, 'text', 'game'),
            GazetteEntry::class => $this->extractColumnData( $data, 'text', 'gazette'),
            HeroSkill::class => $this->extractColumnData( $data, ['title','description'], 'game'),
            ForumThreadTag::class => $this->extractColumnData( $data, 'label', 'global'),
            CouncilEntry::class => $this->extractColumnData( $data, 'text', 'council'),
            default => true,
        };
    }

    public function enterNode(Node $node): ?Node
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        if (is_a( (string)$node->namespacedName, FixtureProcessorInterface::class, true )) {

            $chain = $this->lookup->findChainClassByProvider( (string)$node->namespacedName );
            if (!$chain) return null;

            try {
                /** @var FixtureChainInterface $provider */
                $provider = $this->container->get( $chain );

                $this->extractData( $provider, $provider->data( (string)$node->namespacedName ) );
            } catch (\Throwable $t) { return null; }
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        return null;
    }

    public function afterTraverse(array $nodes): ?Node
    {
        return null;
    }
}
