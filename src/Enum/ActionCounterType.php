<?php

namespace App\Enum;

enum ActionCounterType: int {

    case Well        		= 1;
    case HomeKitchen 		= 2;
    case HomeLab     		= 3;
    case Trash       		= 4;
    case Complaint   		= 5;
    case RemoveLog   		= 6;
    case SendPMItem  		= 7;
    case SandballHit 		= 8;
    case Clothes     		= 9;
    case HomeCleanup 		= 10;
    case Shower      		= 11;
    case ReceiveHeroic 		= 12;
    case Pool      			= 13;
    case SpecialDigScavenger = 14;
    case DumpInsertion 		= 15;
    case SpecialActionTech	= 16;
    case SpecialActionSurv	= 17;
    case SpecialActionHunter	= 18;
    case SpecialActionAPLoan	= 19;
    case AnonMessage     	= 20;
    case AnonPost         	= 21;
    case PurgeLog   		= 22;
    case TamerClinicUsed 	= 23;
    case LastAutoghoulAt 	= 24;
    case ReceiveXP 	= 25;

    /**
     * @return ActionCounterType[]
     */
    public static function perGameActionTypes(): array {
        return [
            self::RemoveLog,
            self::PurgeLog,
            self::Pool,
            self::AnonMessage,
            self::AnonPost,
            self::TamerClinicUsed,
            self::LastAutoghoulAt,
        ];
    }

    public function isPerGameActionType(): bool {
        return in_array( $this, self::perGameActionTypes(), true );
    }
}