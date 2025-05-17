<?php


namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;

readonly class Locksmith {

    private LockFactory $lock_factory;

    public function __construct(ParameterBagInterface $params) {
        $this->lock_factory = new LockFactory(
            new FlockStore( "{$params->get('kernel.project_dir')}/var/tmp/flock" )
        );
    }

    public function getLock( string $name, ?float $ttl = null ): LockInterface {
        return $this->lock_factory->createLock( $name, $ttl );
    }

    public function getAcquiredLock( string $name, ?float $ttl = null ): ?LockInterface {
        $lock = $this->getLock( $name, $ttl );
        if ($lock->acquire()) return $lock;
        else return null;
    }

    public function waitForLock( string $name, ?float $ttl = null ): LockInterface {
        $lock = $this->getLock( $name, $ttl );
        $lock->acquire( true );
        return $lock;
    }

}
