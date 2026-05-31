<?php

namespace App\Service\Actions\External;

use App\Entity\ExternalAccessTokens;
use App\Enum\Configuration\ExternalTokenPurpose;
use App\Enum\Configuration\ExternalTokenType;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\ConfMaster;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Gitlab\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

readonly class GetExternalTokenWithFallbackAction
{
    public function __construct(
        private ConfMaster $conf,
        private EntityManagerInterface $em,
        private ParameterBagInterface $params
    ) { }

    /**
     * @param ExternalTokenType $type
     * @param ExternalTokenPurpose|null $purpose
     * @param MyHordesSetting|null $fallback
     * @return ArrayCollection<string>|PersistentCollection<int, string>
     * @throws Exception
     */
    public function __invoke(ExternalTokenType $type, ?ExternalTokenPurpose $purpose, ?MyHordesSetting $fallback ): ArrayCollection|PersistentCollection
    {
        $criteria = Criteria::create()
            ->andWhere( Criteria::expr()->eq( 'active', true ) )
            ->andWhere( Criteria::expr()->eq( 'type', $type ) )
            ->andWhere( Criteria::expr()->eq( 'env', $this->params->get('kernel.environment') ) );

        if ($purpose) $criteria = $criteria->andWhere( Criteria::expr()->eq( 'purpose', $purpose ) );

        $tokens = $this->em
            ->getRepository(ExternalAccessTokens::class)
            ->matching($criteria)
            ->map( fn(ExternalAccessTokens $token) => $token->getToken() );

        if ($fallback && $tokens->isEmpty()) {
            $fallback_token = $this->conf->getGlobalConf()->get( $fallback );
            if ($fallback_token) return new ArrayCollection( [$fallback_token] );
        }

        return $tokens;
    }
}
