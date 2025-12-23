<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Bar;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Baz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ResponseEventTest extends TestCase
{
    public function testGetControllerAttributesWithoutControllerEvent()
    {
        $kernel = new TestHttpKernel();
        $request = new Request();
        $response = new Response();

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->assertEquals([], $event->getControllerAttributes());
        $this->assertEquals([], $event->getControllerAttributes(Bar::class));
    }

    public function testGetControllerAttributesWithControllerEvent()
    {
        $kernel = new TestHttpKernel();
        $request = new Request();
        $response = new Response();

        $controller = [new AttributeController(), '__invoke'];
        $controllerEvent = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response, $controllerEvent);

        $expected = [
            Bar::class => [
                new Bar('class'),
                new Bar('method'),
            ],
            Baz::class => [
                new Baz(),
            ],
        ];

        $this->assertEquals($expected, $event->getControllerAttributes());
    }

    public function testGetControllerAttributesByClassName()
    {
        $kernel = new TestHttpKernel();
        $request = new Request();
        $response = new Response();

        $controller = [new AttributeController(), '__invoke'];
        $controllerEvent = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response, $controllerEvent);

        $expected = [
            new Bar('class'),
            new Bar('method'),
        ];

        $this->assertEquals($expected, $event->getControllerAttributes(Bar::class));
    }

    public function testGetControllerAttributesByInvalidClassName()
    {
        $kernel = new TestHttpKernel();
        $request = new Request();
        $response = new Response();

        $controller = [new AttributeController(), '__invoke'];
        $controllerEvent = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response, $controllerEvent);

        $this->assertEquals([], $event->getControllerAttributes(\stdClass::class));
    }
}
