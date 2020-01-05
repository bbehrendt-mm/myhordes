<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\Requirement;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Response\AjaxResponse;
use App\Structures\ItemRequest;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class ActionHandler
{
    private $entity_manager;
    private $status_factory;
    private $citizen_handler;
    private $inventory_handler;

    public function __construct( EntityManagerInterface $em, StatusFactory $sf, CitizenHandler $ch, InventoryHandler $ih)
    {
        $this->entity_manager = $em;
        $this->status_factory = $sf;
        $this->citizen_handler = $ch;
        $this->inventory_handler = $ih;
    }

    const ActionValidityNone = 1;
    const ActionValidityHidden = 2;
    const ActionValidityCrossed = 3;
    const ActionValidityAllow = 4;
    const ActionValidityFull = 5;

    protected function evaluate( Citizen $citizen, Item $item, ItemAction $action, ?string &$message ): int {

        if (!$item->getPrototype()->getActions()->contains( $action )) return self::ActionValidityNone;

        $current_state = self::ActionValidityFull;
        foreach ($action->getRequirements() as $meta_requirement) {

            $last_state = $current_state;

            $this_state = self::ActionValidityNone;
            switch ($meta_requirement->getFailureMode()) {
                case Requirement::MessageOnFail: $this_state = self::ActionValidityAllow; break;
                case Requirement::CrossOnFail: $this_state = self::ActionValidityCrossed; break;
                case Requirement::HideOnFail: $this_state = self::ActionValidityHidden; break;
            }

            if ($status = $meta_requirement->getStatusRequirement()) {
                $status_is_active = $citizen->getStatus()->contains( $status->getStatus() );
                if ($status_is_active !== $status->getEnabled()) $current_state = min( $current_state, $this_state );
            }

            if ($item_condition = $meta_requirement->getItem()) {
                $item_str = ($is_prop = (bool)$item_condition->getProperty())
                    ? $item_condition->getProperty()->getName()
                    : $item_condition->getPrototype()->getName();

                if (empty($this->inventory_handler->fetchSpecificItems( $citizen->getInventory(),
                    [new ItemRequest($item_str, 1, null, null, $is_prop)]
                ))) $current_state = min( $current_state, $this_state );
            }

            if ($current_state < $last_state) $message = $meta_requirement->getFailureText();

        }

        return $current_state;

    }

    /**
     * @param Citizen $citizen
     * @param Item $item
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     */
    public function getAvailableItemActions(Citizen $citizen, Item &$item, ?array &$available, ?array &$crossed ) {

        $available = $crossed = [];
        if ($item->getBroken()) return;

        foreach ($item->getPrototype()->getActions() as $action) {
            $mode = $this->evaluate( $citizen, $item, $action, $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $action;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $action;
        }

    }

    const ErrorNone = 0;
    const ErrorActionUnregistered = ErrorHelper::BaseActionErrors + 1;
    const ErrorActionForbidden    = ErrorHelper::BaseActionErrors + 2;
    const ErrorActionImpossible   = ErrorHelper::BaseActionErrors + 3;

    public function execute( Citizen &$citizen, Item &$item, ItemAction $action, ?string &$message ): int {

        $mode = $this->evaluate( $citizen, $item, $action, $tx );
        if ($mode <= self::ActionValidityNone)    return self::ErrorActionUnregistered;
        if ($mode <= self::ActionValidityCrossed) return self::ErrorActionImpossible;
        if ($mode <= self::ActionValidityAllow) {
            $message = $tx;
            return self::ErrorActionForbidden;
        }
        if ($mode != self::ActionValidityFull) return self::ErrorActionUnregistered;

        foreach ($action->getResults() as $result) {

            if ($status = $result->getStatus()) {

                if ($status->getInitial() && $status->getResult()) {
                    if ($citizen->getStatus()->contains( $status->getInitial() )) {
                        $citizen->removeStatus( $status->getInitial() );
                        $citizen->addStatus( $status->getResult() );
                    }
                }
                elseif ($status->getInitial()) $citizen->removeStatus( $status->getInitial() );
                elseif ($status->getResult())  $citizen->addStatus( $status->getResult() );

            }

            if ($ap = $result->getAp())
                $this->citizen_handler->setAP( $citizen, !$ap->getMax(), $ap->getMax() ? ( $this->citizen_handler->getMaxAP($citizen) + $ap->getAp() ) : $ap->getAp() );

            if ($item_result = $result->getItem()) {

                if ($item_result->getMorph()) $item->setPrototype( $item_result->getMorph() );
                elseif ($item_result->getConsume()) $citizen->getInventory()->removeItem( $item );

            }
        }

        return self::ErrorNone;
    }
}