<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Item;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Response\AjaxResponse;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
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

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 */
class TownController extends AbstractController implements GameInterfaceController, GameProfessionInterfaceController
{
    protected $entity_manager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entity_manager = $em;
    }

    protected function getActiveCitizen(): Citizen {
        return $this->entity_manager->getRepository(Citizen::class)->findActiveByUser($this->getUser());
    }

    protected function addDefaultTwigArgs( ?string $section = null, ?array $data = null ): array {
        $data = $data ?? [];
        $data['menu_section'] = $section;
        return $data;
    }

    /**
     * @Route("jx/town/dashboard", name="town_dashboard")
     * @return Response
     */
    public function dashboard(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs(null, [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/house", name="town_house")
     * @return Response
     */
    public function house(): Response
    {
        return $this->render( 'ajax/game/town/home.html.twig', $this->addDefaultTwigArgs('house', [
            'rucksack' => $this->getActiveCitizen()->getInventory(),
            'rucksack_size' => 4,
            'chest' => $this->getActiveCitizen()->getHome()->getChest(),
            'chest_size' => 4,
        ]) );
    }

    /**
     * @Route("api/town/house/item", name="town_house_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function item_house_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $item_id = (int)$parser->get('item', -1);
        $direction = $parser->get('direction', '');

        $item = $this->entity_manager->getRepository(Item::class)->find( $item_id );
        if ($item && $item->getInventory() && in_array($direction, ['up','down'])) {
            $citizen = $this->getActiveCitizen();
            $inv_source = $direction === 'up'   ? $this->getActiveCitizen()->getHome()->getChest() : $this->getActiveCitizen()->getInventory();
            $inv_target = $direction === 'down' ? $this->getActiveCitizen()->getHome()->getChest() : $this->getActiveCitizen()->getInventory();
            if ($handler->transferItem(
                $citizen,
                $item,$inv_source, $inv_target
            )) {
                try {
                    $this->entity_manager->persist($item);
                    $this->entity_manager->flush();
                } catch (Exception $e) {
                    return AjaxResponse::error('db_error');
                }
                return AjaxResponse::success();
            }
        }
        return AjaxResponse::error('invalid_transfer');
    }

    /**
     * @Route("jx/town/well", name="town_well")
     * @return Response
     */
    public function well(): Response
    {
        return $this->render( 'ajax/game/town/well.html.twig', $this->addDefaultTwigArgs('well', [
            'rations_left' => $this->getActiveCitizen()->getTown()->getWell(),
            'first_take' => $this->getActiveCitizen()->getWellCounter()->getTaken() === 0,
            'allow_take' => $this->getActiveCitizen()->getWellCounter()->getTaken() < 2, //ToDo: Fix the count!
        ]) );
    }

    /**
     * @Route("api/well/item", name="town_well_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @param ItemFactory $factory
     * @return Response
     */
    public function well_api(JSONRequestParser $parser, InventoryHandler $handler, ItemFactory $factory): Response {
        $direction = $parser->get('direction', '');

        if (in_array($direction, ['up','down'])) {
            $citizen = $this->getActiveCitizen();

            $town = $citizen->getTown();
            $wellLock = $citizen->getWellCounter();

            if ($direction == 'up') {

                if ($town->getWell() <= 0) return AjaxResponse::error('empty');
                if ($wellLock->getTaken() >= 2) return AjaxResponse::error('locked'); //ToDo: Fix the count!

                $inv_target = $citizen->getInventory();
                $inv_source = null;
                $item = $factory->createItem( 'water_#00' );

                if ($handler->transferItem(
                    $citizen,
                    $item,$inv_source, $inv_target
                )) {
                    $wellLock->setTaken( $wellLock->getTaken()+1 );
                    $town->setWell( $town->getWell()-1 );
                    try {
                        $this->entity_manager->persist($item);
                        $this->entity_manager->persist($town);
                        $this->entity_manager->persist($wellLock);
                        $this->entity_manager->flush();
                    } catch (Exception $e) {
                        return AjaxResponse::error('db_error');
                    }
                    return AjaxResponse::success();
                }

            }

            // ToDo Sent water back

            $inv_source = $direction === 'up'   ? $this->getActiveCitizen()->getHome()->getChest() : $this->getActiveCitizen()->getInventory();
            $inv_target = $direction === 'down' ? $this->getActiveCitizen()->getHome()->getChest() : $this->getActiveCitizen()->getInventory();
            if ($handler->transferItem(
                $citizen,
                $item,$inv_source, $inv_target
            )) {
                try {
                    $this->entity_manager->persist($item);
                    $this->entity_manager->flush();
                } catch (Exception $e) {
                    return AjaxResponse::error('db_error');
                }
                return AjaxResponse::success();
            }
        }
        return AjaxResponse::error('invalid_transfer');
    }


    /**
     * @Route("jx/town/bank", name="town_bank")
     * @return Response
     */
    public function bank(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('bank', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/citizens", name="town_citizens")
     * @return Response
     */
    public function citizens(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('citizens', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/constructions", name="town_constructions")
     * @return Response
     */
    public function constructions(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('constructions', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/door", name="town_door")
     * @return Response
     */
    public function door(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('door', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }
}
