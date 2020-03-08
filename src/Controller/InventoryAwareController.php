<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use App\Entity\ItemTargetDefinition;
use App\Entity\Recipe;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ErrorHelper;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use App\Structures\BankItem;
use App\Structures\ItemRequest;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class InventoryAwareController extends AbstractController implements GameInterfaceController, GameProfessionInterfaceController, GameAliveInterfaceController
{
    protected $entity_manager;
    protected $inventory_handler;
    protected $citizen_handler;
    protected $action_handler;
    protected $translator;

    public function __construct(
        EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch, ActionHandler $ah,
        TranslatorInterface $translator)
    {
        $this->entity_manager = $em;
        $this->inventory_handler = $ih;
        $this->citizen_handler = $ch;
        $this->action_handler = $ah;
        $this->translator = $translator;
    }

    protected function addDefaultTwigArgs( ?string $section = null, ?array $data = null ): array {
        $data = $data ?? [];
        $data['menu_section'] = $section;

        $data['ap'] = $this->getActiveCitizen()->getAp();
        $data['max_ap'] = $this->citizen_handler->getMaxAP( $this->getActiveCitizen() );
        $data['bp'] = $this->getActiveCitizen()->getBp();
        $data['max_bp'] = $this->citizen_handler->getMaxBP( $this->getActiveCitizen() );
        $data['status'] = $this->getActiveCitizen()->getStatus();
        $data['rucksack'] = $this->getActiveCitizen()->getInventory();
        $data['rucksack_size'] = $this->inventory_handler->getSize( $this->getActiveCitizen()->getInventory() );
        return $data;
    }

    protected function getActiveCitizen(): Citizen {
        return $this->entity_manager->getRepository(Citizen::class)->findActiveByUser($this->getUser());
    }

    protected function getItemActions(): array {
        $ret = [];

        /**
         * @param Inventory[] $inventories
         * @param ItemTargetDefinition $definition
         * @return array
         */
        $get_targets = function ( array $inventories, ItemTargetDefinition $definition ): array {
            $targets = [];
            foreach ($inventories as &$inv)
                foreach ($inv->getItems() as &$item)
                    if ($this->action_handler->targetDefinitionApplies($item,$definition))
                        $targets[] = $item;
            return $targets;
        };

        $av_inv = [$this->getActiveCitizen()->getInventory(), $this->getActiveCitizen()->getZone() ? $this->getActiveCitizen()->getZone()->getFloor() : $this->getActiveCitizen()->getHome()->getChest()];

        foreach ($this->getActiveCitizen()->getInventory()->getItems() as $item) if (!$item->getBroken()) {

            $this->action_handler->getAvailableItemActions( $this->getActiveCitizen(), $item, $available, $crossed );
            if (empty($available) && empty($crossed)) continue;

            foreach ($available as $a) $ret[] = [ 'item' => $item, 'action' => $a, 'targets' => $a->getTarget() ? $get_targets( $av_inv, $a->getTarget() ) : null, 'crossed' => false ];
            foreach ($crossed as $c)   $ret[] = [ 'item' => $item, 'action' => $c, 'targets' => null, 'crossed' => true ];
        }

        return $ret;
    }

    protected function getItemCombinations(bool $inside): array {
        $town = $this->getActiveCitizen()->getTown();
        $source_inv = [ $this->getActiveCitizen()->getInventory(), $this->getActiveCitizen()->getZone() ? $this->getActiveCitizen()->getZone()->getFloor() : $this->getActiveCitizen()->getHome()->getChest() ];

        $recipes = $this->entity_manager->getRepository(Recipe::class)->findByType( [Recipe::ManualAnywhere, $inside ? Recipe::ManualInside : Recipe::ManualOutside] );
        $out = [];
        $source_db = [];
        foreach ($recipes as $recipe) {
            /** @var Recipe $recipe */
            $found_provoking = false;
            foreach ($recipe->getProvoking() as $proto)
                if ($this->inventory_handler->countSpecificItems( $source_inv, $proto )) {
                    $found_provoking = true;
                    break;
                }

            if (!$found_provoking) continue;
            $out[] = $recipe;

            if ($recipe->getSource())
                foreach ($recipe->getSource()->getEntries() as $entry)
                    /** @var ItemGroupEntry $entry */
                    if (!isset( $source_db[ $entry->getPrototype()->getId() ] ))
                        $source_db[ $entry->getPrototype()->getId() ] = $this->inventory_handler->countSpecificItems( $source_inv, $entry->getPrototype() );
        }

        return [ 'recipes' => $out, 'source_items' => $source_db ];
    }

    protected function renderInventoryAsBank( Inventory $inventory ) {
        $qb = $this->entity_manager->createQueryBuilder();
        $data = $qb
            ->select('i.id', 'c.label as l1', 'cr.label as l2', 'COUNT(i) as n')->from('App:Item','i')
            ->where('i.inventory = :inv')->setParameter('inv', $inventory)
            ->groupBy('i.prototype', 'i.broken')
            ->leftJoin('App:ItemPrototype', 'p', Join::WITH, 'i.prototype = p.id')
            ->leftJoin('App:ItemCategory', 'c', Join::WITH, 'p.category = c.id')
            ->leftJoin('App:ItemCategory', 'cr', Join::WITH, 'c.parent = cr.id')
            ->orderBy('cr.ordering','ASC')->addOrderBy('c.ordering', 'ASC')->addOrderBy('p.id', 'ASC')->addOrderBy('i.id', 'ASC')
            ->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        $final = [];
        foreach ($data as $entry) {
            $label = $entry['l2'] ?? $entry['l1'] ?? 'Sonstiges';
            if (!isset($final[$label])) $final[$label] = [];
            $final[$label][] = new BankItem( $this->entity_manager->getRepository(Item::class)->find( $entry['id'] ), $entry['n'] );
        }
        return $final;
    }

    public function generic_item_api(Inventory &$up_target, Inventory &$down_target, bool $allow_down_all, JSONRequestParser $parser, InventoryHandler $handler): Response {
        $item_id = (int)$parser->get('item', -1);
        $direction = $parser->get('direction', '');
        $allowed_directions = ['up','down'];
        if ($allow_down_all) $allowed_directions[] = 'down-all';
        $item = $item_id < 0 ? null : $this->entity_manager->getRepository(Item::class)->find( $item_id );

        $carrier_items = ['bag_#00','bagxl_#00','cart_#00','pocket_belt_#00'];

        $drop_carriers = false;
        if ($direction === 'down' && $allow_down_all && in_array($item->getPrototype()->getName(), $carrier_items)) {
            $direction = 'down-all';
            $drop_carriers = true;
        }

        if (in_array($direction, $allowed_directions)) {
            $citizen = $this->getActiveCitizen();
            $inv_source = $direction === 'up' ? $down_target : $up_target;
            $inv_target = $direction !== 'up' ? $down_target : $up_target;

            $items = [];
            if ($direction !== 'down-all') {
                $item = $this->entity_manager->getRepository(Item::class)->find( $item_id );
                if ($item && $item->getInventory()) $items = [$item];
            } else
                $items = $drop_carriers ? $citizen->getInventory()->getItems() : array_filter($citizen->getInventory()->getItems()->getValues(), function(Item $i) use ($carrier_items) {
                    return !in_array($i->getPrototype()->getName(), $carrier_items);
                });

            $errors = [];
            foreach ($items as &$current_item)
                if (($error = $handler->transferItem(
                        $citizen,
                        $current_item,$inv_source, $inv_target
                    )) === InventoryHandler::ErrorNone) {
                    if ($current_item->getInventory())
                        $this->entity_manager->persist($current_item);
                    else $this->entity_manager->remove($current_item);
                } else $errors[] = $error;

            if (count($errors) < count($items)) {
                try {
                    $this->entity_manager->flush();
                } catch (Exception $e) {
                    return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
                }
                return AjaxResponse::success();
            } else return AjaxResponse::error($errors[0]);
        }
        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
    }

    public function generic_recipe_api(JSONRequestParser $parser, ActionHandler $handler): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!$parser->has_all(['id'], true))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $id = (int)$parser->get('id');

        /** @var Recipe $recipe */
        $recipe = $this->entity_manager->getRepository(Recipe::class)->find( $id );
        if ($recipe === null || !in_array($recipe->getType(), [Recipe::ManualAnywhere, Recipe::ManualOutside, Recipe::ManualInside]))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if (($error = $handler->execute_recipe( $citizen, $recipe, $remove, $message )) !== ActionHandler::ErrorNone )
            return AjaxResponse::error( $error );
        else try {
            $this->entity_manager->persist($town);
            $this->entity_manager->persist($citizen);
            foreach ($remove as $e) $this->entity_manager->remove( $e );
            $this->entity_manager->flush();
            if ($message) $this->addFlash( 'notice', $message );
            return AjaxResponse::success();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }
    }

    public function generic_action_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $item_id =   (int)$parser->get('item',   -1);
        $target_id = (int)$parser->get('target', -1);
        $action_id = (int)$parser->get('action', -1);

        /** @var Item|null $item */
        $item   = ($item_id < 0)   ? null : $this->entity_manager->getRepository(Item::class)->find( $item_id );
        /** @var Item|null $target */
        $target = ($item_id < 0)   ? null : $this->entity_manager->getRepository(Item::class)->find( $target_id );
        /** @var ItemAction|null $action */
        $action = ($action_id < 0) ? null : $this->entity_manager->getRepository(ItemAction::class)->find( $action_id );

        if ( !$item || !$action || $item->getBroken() || ($action->getTarget() && !$target) ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $citizen = $this->getActiveCitizen();

        $zone = $citizen->getZone();
        if ($zone && $zone->getX() === 0 && $zone->getY() === null ) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $secondary_inv = $zone ? $zone->getFloor() : $citizen->getHome()->getChest();
        if (!$citizen->getInventory()->getItems()->contains( $item ) && !$secondary_inv->getItems()->contains( $item )) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        if ($target && !$citizen->getInventory()->getItems()->contains( $target ) && !$secondary_inv->getItems()->contains( $target )) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (($error = $this->action_handler->execute( $citizen, $item, $target, $action, $msg, $remove )) === ActionHandler::ErrorNone) {
            $this->entity_manager->persist($citizen);
            if ($item->getInventory())
                $this->entity_manager->persist($item);
            foreach ($remove as $remove_entry)
                $this->entity_manager->remove($remove_entry);
            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorDatabaseException, ['msg' => $e->getMessage()] );
            }

            if ($msg) $this->addFlash( 'notice', $msg );
        } elseif ($error === ActionHandler::ErrorActionForbidden) {
            if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'game');
            return AjaxResponse::error($error, ['message' => $msg]);
        }
        else return AjaxResponse::error( $error );

        return AjaxResponse::success();
    }
}
