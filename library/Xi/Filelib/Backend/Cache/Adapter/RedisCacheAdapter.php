<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Backend\Cache\Adapter;

use Redis;
use Xi\Filelib\Identifiable;
use Xi\Filelib\RuntimeException;

class RedisCacheAdapter implements CacheAdapter
{
    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @param Redis $redis
     */
    public function __construct(Redis $redis, $prefix = '')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * @param $id
     * @param $className
     * @return Identifiable
     */
    public function findById($id, $className)
    {
        return unserialize(
            $this->redis->get($this->createKeyFromParts($id, $className))
        );
    }

    /**
     * @param array $ids
     * @param $className
     * @return Identifiable[]
     */
    public function findByIds(array $ids, $className)
    {
        $output = array();
        foreach ($ids as $id) {
            $output[] = unserialize(
                $this->redis->get($this->createKeyFromParts($id, $className))
            );
        }
        return $output;
    }

    /**
     * @param Identifiable $identifiable
     */
    public function save(Identifiable $identifiable)
    {
        $this->redis->set(
            $this->createKeyFromIdentifiable($identifiable),
            serialize($identifiable)
        );
    }

    /**
     * @param Identifiable $identifiable
     */
    public function delete(Identifiable $identifiable)
    {
        $this->redis->delete($this->createKeyFromIdentifiable($identifiable));
    }

    /**
     * @param Identifiable $identifiable
     * @return string
     * @throws RuntimeException
     */
    public function createKeyFromIdentifiable(Identifiable $identifiable)
    {
        if (!$identifiable->getId()) {
            throw new RuntimeException("Identifiable is missing an id");
        }

        return $this->createKeyFromParts($identifiable->getId(), get_class($identifiable));
    }

    /**
     * @param string $id
     * @param string $className
     * @return string
     */
    public function createKeyFromParts($id, $className)
    {
        return $this->prefix . $className . '___' . $id;
    }

    public function clear()
    {
        $this->redis->flush();
    }
}
