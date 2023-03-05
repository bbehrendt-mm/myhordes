<?php


namespace App\Service;


use App\Entity\Item;
use App\Entity\ItemPrototype;
use Doctrine\ORM\EntityManagerInterface;

class ErrorHelper
{
    const BaseInventoryErrors     = 100;
    const BaseJobErrors           = 200;
    const BaseTownErrors          = 300;
    const BaseTownSelectionErrors = 400;
    const BaseUserErrors          = 500;
    const BaseActionErrors        = 600;
    const BaseBeyondErrors        = 700;
    const BaseSoulErrors          = 800;
    const BaseGhostErrors         = 900;
    const BaseAPIErrors           = 1000;

    const BaseForumErrors         = 1100;

    const ErrorBannedFromForum    = 1201;

    const BaseAvatarErrors        = 1300;

    const BaseMessageErrors       = 1400;

    const ErrorInvalidRequest    = 1;
    const ErrorDatabaseException = 2;
    const ErrorInternalError     = 3;
    const ErrorPermissionError   = 4;
    const ErrorSendingEmail      = 5;

    const ErrorNoAP                      = 51;
    const ErrorActionNotAvailable        = 52;
    const ErrorItemsMissing              = 53;
    const ErrorMustBeHero                = 54;
    const ErrorActionNotAvailableWounded = 55;
    const ErrorActionNotAvailableSP      = 56;
    const ErrorActionNotAvailableTerror  = 57;
    const ErrorRateLimited               = 58;
    const ErrorNoMP                      = 59;
    const ErrorActionNotAvailableImpersonator = 60;

    const ErrorActionNotAvailableBanished = 62;

    const ErrorBlockedByUser             = 71;
}