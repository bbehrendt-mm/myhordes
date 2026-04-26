<?php


namespace App\Service;

use Redis;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\RedisStore;

readonly class Locksmith {

    private LockFactory $lock_factory;

    public function __construct(Redis $redis) {
        $this->lock_factory = new LockFactory(
            new RedisStore($redis)
        );
    }

    public function getLock( string $name, ?float $ttl = null ): LockInterface {
        return $this->lock_factory->createLock( $name, $ttl ?? 300.0 );
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
