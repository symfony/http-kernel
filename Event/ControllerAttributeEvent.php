<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Event dispatched for each controller attribute.
 *
 * @template T of object
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ControllerAttributeEvent implements StoppableEventInterface
{
    private string|array|object|null $controller;

    /**
     * @param T $attribute
     */
    public function __construct(
        /** @var T */
        public readonly object $attribute,
        public readonly KernelEvent $kernelEvent,
    ) {
        $this->controller = match (true) {
            $kernelEvent instanceof ControllerEvent => $kernelEvent->getController(),
            $kernelEvent instanceof ControllerArgumentsEvent => $kernelEvent->getController(),
            default => null,
        };
    }

    public function isPropagationStopped(): bool
    {
        if ($this->kernelEvent->isPropagationStopped()) {
            return true;
        }

        if (!$this->controller) {
            return false;
        }

        $controller = match (true) {
            $this->kernelEvent instanceof ControllerEvent => $this->kernelEvent->getController(),
            $this->kernelEvent instanceof ControllerArgumentsEvent => $this->kernelEvent->getController(),
        };

        return $controller instanceof \Closure ? $controller != $this->controller : $controller !== $this->controller;
    }
}
