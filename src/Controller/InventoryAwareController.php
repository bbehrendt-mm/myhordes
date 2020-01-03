<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Response\AjaxResponse;
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

    public function __construct(EntityManagerInterface $em, InventoryHandler $ih)
    {
        $this->entity_manager = $em;
        $this->inventory_handler = $ih;
    }

    protected function getActiveCitizen(): Citizen {
        return $this->entity_manager->getRepository(Citizen::class)->findActiveByUser($this->getUser());
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

        if (in_array($direction, $allowed_directions)) {
            $citizen = $this->getActiveCitizen();
            $inv_source = $direction === 'up' ? $down_target : $up_target;
            $inv_target = $direction !== 'up' ? $down_target : $up_target;

            $items = [];
            if ($direction !== 'down-all') {
                $item = $this->entity_manager->getRepository(Item::class)->find( $item_id );
                if ($item && $item->getInventory()) $items = [$item];
            } else
                $items = $citizen->getInventory()->getItems();

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
                    return AjaxResponse::error('db_error');
                }
            }

            if (count($errors) !== 1) return AjaxResponse::success();
            else switch ($errors[0]) {
                case InventoryHandler::ErrorInvalidTransfer: return AjaxResponse::error('invalid_transfer');
                case InventoryHandler::ErrorInventoryFull:   return AjaxResponse::error('full');
                case InventoryHandler::ErrorHeavyLimitHit:   return AjaxResponse::error('too_heavy');
                case InventoryHandler::ErrorBankLimitHit:    return AjaxResponse::error('bank_limit');
                case InventoryHandler::ErrorStealLimitHit:   return AjaxResponse::error('steal_limit');
                default: return AjaxResponse::error();
            }
        }
        return AjaxResponse::error('invalid_transfer');
    }
}
