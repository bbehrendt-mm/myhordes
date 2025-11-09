<?php


namespace App\Service;


use App\Entity\Item;
use App\Entity\ItemPrototype;
use Doctrine\ORM\EntityManagerInterface;

class ErrorHelper
{
    const int BaseInventoryErrors     = 100;
    const int BaseJobErrors           = 200;
    const int BaseTownErrors          = 300;
    const int BaseTownSelectionErrors = 400;
    const int BaseUserErrors          = 500;
    const int BaseActionErrors        = 600;
    const int BaseBeyondErrors        = 700;
    const int BaseSoulErrors          = 800;
    const int BaseGhostErrors         = 900;
    const int BaseAPIErrors           = 1000;

    const int BaseForumErrors         = 1100;

    const int ErrorBannedFromForum    = 1201;

    const int BaseAvatarErrors        = 1300;

    const int BaseMessageErrors       = 1400;

    const int ErrorInvalidRequest    = 1;
    const int ErrorDatabaseException = 2;
    const int ErrorInternalError     = 3;
    const int ErrorPermissionError   = 4;
    const int ErrorSendingEmail      = 5;

    const int ErrorNoAP                      = 51;
    const int ErrorActionNotAvailable        = 52;
    const int ErrorItemsMissing              = 53;
    const int ErrorMustBeHero                = 54;
    const int ErrorActionNotAvailableWounded = 55;
    const int ErrorActionNotAvailableSP      = 56;
    const int ErrorActionNotAvailableTerror  = 57;
    const int ErrorRateLimited               = 58;
    const int ErrorNoMP                      = 59;
    const int ErrorActionNotAvailableImpersonator = 60;

    const int ErrorActionNotAvailableBanished = 62;

    const int ErrorBlockedByUser             = 71;
    const int ErrorEncumbered             = 72;
}
