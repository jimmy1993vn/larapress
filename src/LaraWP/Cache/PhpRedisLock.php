<?php

namespace LaraWP\Cache;

use LaraWP\Redis\Connections\PhpRedisConnection;

class PhpRedisLock extends RedisLock
{
    /**
     * Create a new phpredis lock instance.
     *
     * @param \LaraWP\Redis\Connections\PhpRedisConnection $redis
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return void
     */
    public function __construct(PhpRedisConnection $redis, string $name, int $seconds, ?string $owner = null)
    {
        parent::__construct($redis, $name, $seconds, $owner);
    }

    /**
     * {@inheritDoc}
     */
    public function release()
    {
        return (bool)$this->redis->eval(
            LuaScripts::releaseLock(),
            1,
            $this->name,
            ...$this->redis->pack([$this->owner])
        );
    }
}
