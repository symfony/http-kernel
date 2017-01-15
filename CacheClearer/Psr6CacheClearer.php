<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheClearer;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr6CacheClearer implements CacheClearerInterface
{
    /**
     * @var array
     */
    private $pools = array();

    /**
     * Constructor.
     *
     * @param array $pools
     */
    public function __construct(array $pools = array())
    {
        $this->pools = $pools;
    }

    /**
     * Adds an pool.
     *
     * @param CacheItemPoolInterface $pool
     *
     * @deprecated since version 3.3 and will be removed in 4.0. Pass an array of pools indexed by name to the constructor instead.
     */
    public function addPool(CacheItemPoolInterface $pool)
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Pass an array of pools indexed by name to the constructor instead.', __METHOD__), E_USER_DEPRECATED);

        $this->pools[] = $pool;
    }

    /**
     * Returns true if the pool exists, false otherwise.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasPool($name)
    {
        return isset($this->pools[$name]);
    }

    /**
     * Clears all pools for a given name.
     *
     * @param string $name
     *
     * @return CacheItemPoolInterface
     */
    public function clearPool($name)
    {
        if (!isset($this->pools[$name])) {
            throw new \InvalidArgumentException(sprintf('Cache pool not found: %s.', $name));
        }

        return $this->pools[$name]->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function clear($cacheDir)
    {
        foreach ($this->pools as $pool) {
            $pool->clear();
        }
    }
}
