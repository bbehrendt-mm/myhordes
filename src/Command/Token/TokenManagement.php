<?php

namespace App\Command\Token;

use App\Enum\Configuration\ExternalTokenPurpose;
use App\Enum\Configuration\ExternalTokenType;
use Doctrine\Common\Collections\Criteria;

trait TokenManagement
{
    private static function generateOverlappingCriteria(string $env, ExternalTokenType $type, ?ExternalTokenPurpose $purpose, string $name): Criteria {
        $criteria = Criteria::create()
            ->where( Criteria::expr()->eq( 'env', $env ) )
            ->andWhere( Criteria::expr()->eq( 'type', $type ) )
            ->andWhere( Criteria::expr()->eq( 'active', true ) );

        if (!$type->isUnique() && $purpose) {
            $criteria->andWhere(Criteria::expr()->eq('purpose', $purpose));
            if (!$purpose->isUnique())
                $criteria->andWhere(Criteria::expr()->eq('name', $name));
        }

        return $criteria;
    }

}
