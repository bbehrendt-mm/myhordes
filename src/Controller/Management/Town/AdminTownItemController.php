<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemCategory;
use App\Entity\ItemPrototype;
use App\Entity\Town;
use App\Entity\Zone;
use App\Enum\ItemPoisonType;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Structures\BankItem;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownItemController extends AdminActionController
{
    protected function renderInventoryAsBank( Inventory $inventory ): array
    {
        $qb = $this->entity_manager->createQueryBuilder();
        $qb
            ->select('i.id', 'c.label as l1', 'cr.label as l2', 'SUM(i.count) as n')->from(Item::class,'i')
            ->where('i.inventory = :inv')->setParameter('inv', $inventory)
            ->groupBy('i.prototype', 'i.broken', 'i.poison')
            ->leftJoin(ItemPrototype::class, 'p', Join::WITH, 'i.prototype = p.id')
            ->leftJoin(ItemCategory::class, 'c', Join::WITH, 'p.category = c.id')
            ->leftJoin(ItemCategory::class, 'cr', Join::WITH, 'c.parent = cr.id')
            ->addOrderBy('c.ordering','ASC')
            ->addOrderBy('p.icon', 'DESC')
            ->addOrderBy('i.id', 'ASC');

        $data = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        $final = [];
        $cache = [];

        foreach ($data as $entry) {
            $label = $entry['l2'] ?? $entry['l1'] ?? 'Sonstiges';
            if (!isset($final[$label])) $final[$label] = [];
            $final[$label][] = [ $entry['id'], $entry['n'] ];
            $cache[] = $entry['id'];
        }

        $item_list = $this->entity_manager->getRepository(Item::class)->findAllByIds($cache);
        foreach ( $final as $label => &$entries )
            $entries = array_map(function( array $entry ) use (&$item_list): BankItem { return new BankItem( $item_list[$entry[0]], $entry[1] ); }, $entries);

        return $final;
    }

    /**
     * @param Town $town
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/bank', name: 'admin_town_bank')]
    #[IsGranted('spy', 'town')]
    public function town_explorer_bank(Town $town): Response {
		return $this->render('ajax/manage/towns/explorer_bank.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'itemPrototypes' => $this->getOrderedItemPrototypes($this->getUser()->getAdminLang() ?? $this->getUser()->getLanguage()),
			'tab' => "bank",
			'bank' => $this->renderInventoryAsBank($town->getBank()),
		])));
	}

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @param ItemFactory $itemFactory
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/item', name: 'admin_town_item')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_item_action(Town $town, JSONRequestParser $parser, InventoryHandler $handler, ItemFactory $itemFactory): Response
    {
        $item_id = $parser->get('item');
        $change = $parser->get('change');
        $qty = $parser->get('qty', 1);
        if($qty <= 0)
            $qty = 1;

        $item = $this->entity_manager->getRepository(Item::class)->find($item_id);

        if ($change == 'add') {
            for($i = 0 ; $i < $qty ; $i++)
                $handler->forceMoveItem($town->getBank(), $itemFactory->createItem($item->getPrototype()->getName()));
        } else {
            $handler->forceRemoveItem($item, $qty);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->persist($town->getBank());
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @param ItemFactory $itemFactory
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/spawn_item', name: 'admin_spawn_item')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function spawn_item(Town $town, JSONRequestParser $parser, InventoryHandler $handler, ItemFactory $itemFactory): Response
    {
        $prototype_id = $parser->get('prototype');
        $number = $parser->get_int('number');
        $targets = $parser->get_array('targets');

        $conf = $parser->get_array('conf');
        $poison = $conf['poison'] ?? false;
        if ($poison > 1) $poison = ItemPoisonType::from( $poison );
        $broken = $conf['broken'] ?? false;
        $essential = $conf['essential'] ?? false;
        $hidden = $conf['hidden'] ?? false;

        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var ItemPrototype $itemPrototype */
        if ($prototype_id == "all") {
            $itemPrototype = $this->entity_manager->getRepository(ItemPrototype::class)->findAll();
        } else {
            $itemPrototype = $this->entity_manager->getRepository(ItemPrototype::class)->find($prototype_id);
            if (!$itemPrototype) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!is_array($itemPrototype))
            $itemPrototype = [$itemPrototype];

        /** @var Inventory[] $inventories */
        $inventories = [];
        $bank_mode = false;

        foreach (array_unique($targets['chest'] ?? []) as $target) {
            /** @var CitizenHome $home */
            $home = $this->entity_manager->getRepository(CitizenHome::class)->find($target);
            if (!$home || $home->getCitizen()->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $home->getChest();
        }

        foreach (array_unique($targets['rucksack'] ?? []) as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $citizen->getInventory();
        }

        foreach (array_unique($targets['zone'] ?? []) as $target) {
            /** @var Zone $zone */
            $zone = $this->entity_manager->getRepository(Zone::class)->find($target);
            if (!$zone || $zone->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $zone->getFloor();
        }

        foreach (array_unique($targets['bank'] ?? []) as $target) {
            if ($target !== $town->getId()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories = [$town->getBank()];
            $bank_mode = true;
        }

        foreach ($inventories as $inventory) {
            if ($bank_mode)
                foreach ($itemPrototype as $proto)
                    $handler->forceMoveItem($inventory, $itemFactory->createItem($proto->getName(), $broken, $poison)->setEssential($essential)->setHidden(false)->setCount( $number ), max(1,$number));
            else for ($i = 0; $i < $number; $i++) {
                if ($hidden && $inventory->getZone()) $inventory->getZone()->setItemsHiddenAt( new \DateTimeImmutable() );
                foreach ($itemPrototype as $proto) {
                    $handler->forceMoveItem($inventory, $itemFactory->createItem($proto->getName(), $broken, $poison)->setEssential($essential)->setHidden($hidden && $inventory->getZone()));
                }

            }
            if ($hidden && $inventory->getZone()) $this->entity_manager->persist($inventory->getZone());
            $this->entity_manager->persist($inventory);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }
}
