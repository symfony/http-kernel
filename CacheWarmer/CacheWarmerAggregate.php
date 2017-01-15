<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

/**
 * Aggregates several cache warmers into a single one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheWarmerAggregate implements CacheWarmerInterface
{
    /**
     * @var array
     */
    protected $warmers = array();

    /**
     * @var bool
     */
    protected $optionalsEnabled = false;

    /**
     * Constructor.
     *
     * @param array $warmers
     */
    public function __construct(array $warmers = array())
    {
        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
    }

    /**
     * Enables the warmers.
     */
    public function enableOptionalWarmers()
    {
        $this->optionalsEnabled = true;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        foreach ($this->warmers as $warmer) {
            if (!$this->optionalsEnabled && $warmer->isOptional()) {
                continue;
            }

            $warmer->warmUp($cacheDir);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always false
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * Sets warmers.
     *
     * @param array $warmers
     */
    public function setWarmers(array $warmers)
    {
        $this->warmers = array();

        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
    }

    /**
     * Add a warmer.
     *
     * @param CacheWarmerInterface $warmer
     */
    public function add(CacheWarmerInterface $warmer)
    {
        $this->warmers[] = $warmer;
    }
}
