<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemAction;
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

class InventoryAwareController extends AbstractController implements GameInterfaceController, GameProfessionInterfaceController
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
        foreach ($this->getActiveCitizen()->getInventory()->getItems() as $item) if (!$item->getBroken()) {

            $this->action_handler->getAvailableItemActions( $this->getActiveCitizen(), $item, $available, $crossed );
            if (empty($available) && empty($crossed)) continue;

            foreach ($available as $a) $ret[] = [ 'item' => $item, 'action' => $a, 'crossed' => false ];
            foreach ($crossed as $c)   $ret[] = [ 'item' => $item, 'action' => $c, 'crossed' => true ];
        }

        return $ret;
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
                    $this->entity_manager->persist($current_item);
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

    public function generic_action_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $item_id = (int)$parser->get('item', -1);
        $action_id = (int)$parser->get('action', -1);

        $item   = ($item_id < 0)   ? null : $this->entity_manager->getRepository(Item::class)->find( $item_id );
        $action = ($action_id < 0) ? null : $this->entity_manager->getRepository(ItemAction::class)->find( $action_id );

        if ( !$item || !$action || $item->getBroken() ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $citizen = $this->getActiveCitizen();

        if (($error = $this->action_handler->execute( $citizen, $item, $action, $msg, $remove )) === ActionHandler::ErrorNone) {
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
        } elseif ($error === ActionHandler::ErrorActionForbidden) {
            if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'game');
            return AjaxResponse::error($error, ['message' => $msg]);
        }
        else return AjaxResponse::error( $error );

        return AjaxResponse::success();
    }
}
