<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\UnsignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\IsSignatureValidAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtendedSigner;
use Symfony\Component\HttpKernel\Tests\Fixtures\IsSignatureValidAttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\IsSignatureValidAttributeMethodsController;

#[RequiresMethod(UriSigner::class, 'verify')]
class IsSignatureValidAttributeListenerTest extends TestCase
{
    public function testInvokableControllerWithValidSignature()
    {
        $request = new Request();

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            new IsSignatureValidAttributeController(),
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testNoAttributeSkipsValidation()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $signer = $this->createMock(UriSigner::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'noAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testDefaultCheckRequestSucceeds()
    {
        $request = new Request();
        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withDefaultBehavior'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testCheckRequestFailsThrowsHttpException()
    {
        $request = new Request();
        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->willThrowException(new UnsignedUriException());
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withDefaultBehavior'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);

        $this->expectException(UnsignedUriException::class);
        $listener->onKernelControllerArguments($event);
    }

    public function testMultipleAttributesAllValid()
    {
        $request = new Request();

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->exactly(2))->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withMultiple'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationWithStringMethod()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withPostOnly'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationWithArrayMethods()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withGetAndPost'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationSkippedForNonMatchingMethod()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'GET']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $signer = $this->createMock(UriSigner::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withPostOnly'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationWithSigner()
    {
        $request = new Request();
        $signer = $this->createMock(UriSigner::class);
        $customSigner = $this->createMock(UriSigner::class);
        $customSigner->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('app.test.signer')->willReturn($customSigner);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withCustomSigner'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationWithExtendedSigner()
    {
        $request = new Request();
        $signer = $this->createMock(UriSigner::class);
        $extendedSigner = $this->createMock(ExtendedSigner::class);
        $extendedSigner->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('app.test.extended_signer')->willReturn($extendedSigner);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withCustomExtendedSigner'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer, $container);
        $listener->onKernelControllerArguments($event);
    }
}
