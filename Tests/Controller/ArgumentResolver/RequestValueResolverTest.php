<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest;

class RequestValueResolverTest extends TestCase
{
    public function testResolveReturnsMainRequest()
    {
        $resolver = new RequestValueResolver();
        $request = Request::create('/');
        $metadata = new ArgumentMetadata('request', Request::class, false, false, null);

        $this->assertSame([$request], $resolver->resolve($request, $metadata));
    }

    public function testResolveSkipsUnrelatedTypes()
    {
        $resolver = new RequestValueResolver();
        $request = Request::create('/');
        $metadata = new ArgumentMetadata('foo', \stdClass::class, false, false, null);

        $this->assertSame([], $resolver->resolve($request, $metadata));
    }

    public function testResolveSupportsExtendingRequest()
    {
        $resolver = new RequestValueResolver();
        $request = new ExtendingRequest();
        $metadata = new ArgumentMetadata('request', ExtendingRequest::class, false, false, null);

        $this->assertSame([$request], $resolver->resolve($request, $metadata));
    }

    public function testResolveDefersWhenMatchingNamedAttributeExists()
    {
        $resolver = new RequestValueResolver();
        $request = Request::create('/');
        $request->attributes->set('request', Request::create('/other'));

        $metadata = new ArgumentMetadata('request', Request::class, false, false, null);

        $this->assertSame([], $resolver->resolve($request, $metadata));
    }

    public function testResolveIgnoresUnrelatedAttributeName()
    {
        $resolver = new RequestValueResolver();
        $request = Request::create('/');
        $request->attributes->set('foo', Request::create('/other'));

        $metadata = new ArgumentMetadata('request', Request::class, false, false, null);

        $this->assertSame([$request], $resolver->resolve($request, $metadata));
    }
}
