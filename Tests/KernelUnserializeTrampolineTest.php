<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelWithoutBundles;

class KernelUnserializeTrampolineGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class KernelUnserializeTrampolineTest extends TestCase
{
    public function testUnserializeRejectsStringableTrampoline()
    {
        $class = KernelWithoutBundles::class;
        $data = ['environment' => new KernelUnserializeTrampolineGadget(), 'debug' => true];

        $payload = \sprintf('O:%d:"%s":%d:{', \strlen($class), $class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';

        KernelUnserializeTrampolineGadget::$fired = false;

        try {
            unserialize($payload, ['allowed_classes' => [$class, KernelUnserializeTrampolineGadget::class]]);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(KernelUnserializeTrampolineGadget::$fired, '__toString gadget must not fire during unserialize');
    }
}
