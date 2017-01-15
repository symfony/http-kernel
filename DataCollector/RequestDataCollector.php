<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * RequestDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestDataCollector extends DataCollector implements EventSubscriberInterface
{
    /**
     * @var \SplObjectStorage
     */
    protected $controllers;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->controllers = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // attributes are serialized and as they can be anything, they need to be converted to strings.
        $attributes = array();
        $route = '';

        foreach ($request->attributes->all() as $key => $value) {
            if ('_route' === $key) {
                $route = is_object($value) ? $value->getPath() : $value;
                $attributes[$key] = $route;
            } else {
                $attributes[$key] = $value;
            }
        }

        $content = null;

        try {
            $content = $request->getContent();
        } catch (\LogicException $e) {
            // the user already got the request content as a resource
            $content = false;
        }

        $sessionMetadata = array();
        $sessionAttributes = array();
        $session = null;
        $flashes = array();

        if ($request->hasSession()) {
            $session = $request->getSession();

            if ($session->isStarted()) {
                $sessionMetadata['Created'] = date(DATE_RFC822, $session->getMetadataBag()->getCreated());
                $sessionMetadata['Last used'] = date(DATE_RFC822, $session->getMetadataBag()->getLastUsed());
                $sessionMetadata['Lifetime'] = $session->getMetadataBag()->getLifetime();
                $sessionAttributes = $session->all();
                $flashes = $session->getFlashBag()->peekAll();
            }
        }

        $statusCode = $response->getStatusCode();

        $this->data = array(
            'method' => $request->getMethod(),
            'format' => $request->getRequestFormat(),
            'content' => $content,
            'content_type' => $response->headers->get('Content-Type', 'text/html'),
            'status_text' => isset(Response::$statusTexts[$statusCode]) ? Response::$statusTexts[$statusCode] : '',
            'status_code' => $statusCode,
            'request_query' => $request->query->all(),
            'request_request' => $request->request->all(),
            'request_headers' => $request->headers->all(),
            'request_server' => $request->server->all(),
            'request_cookies' => $request->cookies->all(),
            'request_attributes' => $attributes,
            'route' => $route,
            'response_headers' => $response->headers->all(),
            'session_metadata' => $sessionMetadata,
            'session_attributes' => $sessionAttributes,
            'flashes' => $flashes,
            'path_info' => $request->getPathInfo(),
            'controller' => 'n/a',
            'locale' => $request->getLocale(),
        );

        if (isset($this->data['request_headers']['php-auth-pw'])) {
            $this->data['request_headers']['php-auth-pw'] = '******';
        }

        if (isset($this->data['request_server']['PHP_AUTH_PW'])) {
            $this->data['request_server']['PHP_AUTH_PW'] = '******';
        }

        if (isset($this->data['request_request']['_password'])) {
            $this->data['request_request']['_password'] = '******';
        }

        foreach ($this->data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if ('request_headers' === $key || 'response_headers' === $key) {
                $value = array_map(function ($v) {
                    return isset($v[0]) && !isset($v[1]) ? $v[0]: $v;
                }, $value);
            }

            if ('request_server' !== $key && 'request_cookies' !== $key) {
                $this->data[$key] = array_map(array($this, 'cloneVar'), $value);
            }
        }

        if (isset($this->controllers[$request])) {
            $this->data['controller'] = $this->parseController($this->controllers[$request]);
            unset($this->controllers[$request]);
        }

        if (null !== $session && $session->isStarted()) {
            if ($request->attributes->has('_redirected')) {
                $this->data['redirect'] = $session->remove('sf_redirect');
            }

            if ($response->isRedirect()) {
                $session->set('sf_redirect', array(
                    'token' => $response->headers->get('x-debug-token'),
                    'route' => $request->attributes->get('_route', 'n/a'),
                    'method' => $request->getMethod(),
                    'controller' => $this->parseController($request->attributes->get('_controller')),
                    'status_code' => $statusCode,
                    'status_text' => Response::$statusTexts[(int) $statusCode],
                ));
            }
        }
    }

    /**
     * Gets the request method.
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->data['method'];
    }

    /**
     * Returns the path being requested relative to the executed script.
     *
     * @return string
     */
    public function getPathInfo()
    {
        return $this->data['path_info'];
    }

    /**
     * Returns request body parameters ($_POST).
     *
     * @return ParameterBag
     */
    public function getRequestRequest()
    {
        return new ParameterBag($this->data['request_request']);
    }

    /**
     * Returns query string parameters.
     *
     * @return ParameterBag
     */
    public function getRequestQuery()
    {
        return new ParameterBag($this->data['request_query']);
    }

    /**
     * Returns headers (taken from the $_SERVER).
     *
     * @return ParameterBag
     */
    public function getRequestHeaders()
    {
        return new ParameterBag($this->data['request_headers']);
    }

    /**
     * Retrieves server and execution environment parameters ($_SERVER).
     *
     * @param bool $raw
     *
     * @return ParameterBag
     */
    public function getRequestServer($raw = false)
    {
        return new ParameterBag($raw ? $this->data['request_server'] : array_map(array($this, 'cloneVar'), $this->data['request_server']));
    }

    /**
     * Gets cookies ($_COOKIE).
     *
     * @param bool $raw
     *
     * @return ParameterBag
     */
    public function getRequestCookies($raw = false)
    {
        return new ParameterBag($raw ? $this->data['request_cookies'] : array_map(array($this, 'cloneVar'), $this->data['request_cookies']));
    }

    /**
     * Gets attributes.
     *
     * @return ParameterBag
     */
    public function getRequestAttributes()
    {
        return new ParameterBag($this->data['request_attributes']);
    }

    /**
     * Gets response headers.
     *
     * @return ParameterBag
     */
    public function getResponseHeaders()
    {
        return new ParameterBag($this->data['response_headers']);
    }

    /**
     * Gets session metadata.
     *
     * @return array
     */
    public function getSessionMetadata()
    {
        return $this->data['session_metadata'];
    }

    /**
     * Gets session attributes.
     *
     * @return array
     */
    public function getSessionAttributes()
    {
        return $this->data['session_attributes'];
    }

    /**
     * Gets all flash messages.
     *
     * @return array
     */
    public function getFlashes()
    {
        return $this->data['flashes'];
    }

    /**
     * Gets the current response content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->data['content'];
    }

    /**
     * Retrieves Content-type based on the Request.
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->data['content_type'];
    }

    /**
     * Retrieves the status code translation table for the current web response.
     *
     * @return string
     */
    public function getStatusText()
    {
        return $this->data['status_text'];
    }

    /**
     * Retrieves the status code for the current web response.
     *
     * @return int Status code
     */
    public function getStatusCode()
    {
        return $this->data['status_code'];
    }

    /**
     * Gets the request format.
     *
     * @return string The request format
     */
    public function getFormat()
    {
        return $this->data['format'];
    }

    /**
     * Get the locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->data['locale'];
    }

    /**
     * Gets the route name.
     *
     * The _route request attributes is automatically set by the Router Matcher.
     *
     * @return string The route
     */
    public function getRoute()
    {
        return $this->data['route'];
    }

    /**
     * Gets identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->data['route'] ?: (is_array($this->data['controller']) ? $this->data['controller']['class'].'::'.$this->data['controller']['method'].'()' : $this->data['controller']);
    }

    /**
     * Gets the route parameters.
     *
     * The _route_params request attributes is automatically set by the RouterListener.
     *
     * @return array The parameters
     */
    public function getRouteParams()
    {
        if (!isset($this->data['request_attributes']['_route_params'])) {
            return array();
        }

        $data = $this->data['request_attributes']['_route_params'];
        $rawData = $data->getRawData();
        if (!isset($rawData[1])) {
            return array();
        }

        $params = array();
        foreach ($rawData[1] as $k => $v) {
            $params[$k] = $data->seek($k);
        }

        return $params;
    }

    /**
     * Gets the parsed controller.
     *
     * @return array|string The controller as a string or array of data
     *                      with keys 'class', 'method', 'file' and 'line'
     */
    public function getController()
    {
        return $this->data['controller'];
    }

    /**
     * Gets the previous request attributes.
     *
     * @return array|bool A legacy array of data from the previous redirection response
     *                    or false otherwise
     */
    public function getRedirect()
    {
        return isset($this->data['redirect']) ? $this->data['redirect'] : false;
    }

    /**
     * Remembers the controller associated to each request.
     *
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    /**
     * Filters the Response.
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest() || !$event->getRequest()->hasSession() || !$event->getRequest()->getSession()->isStarted()) {
            return;
        }

        if ($event->getRequest()->getSession()->has('sf_redirect')) {
            $event->getRequest()->attributes->set('_redirected', true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'request';
    }

    /**
     * Parse a controller.
     *
     * @param mixed $controller The controller to parse
     *
     * @return array|string An array of controller data or a simple string
     */
    protected function parseController($controller)
    {
        if (is_string($controller) && false !== strpos($controller, '::')) {
            $controller = explode('::', $controller);
        }

        if (is_array($controller)) {
            try {
                $r = new \ReflectionMethod($controller[0], $controller[1]);

                return array(
                    'class' => is_object($controller[0]) ? get_class($controller[0]) : $controller[0],
                    'method' => $controller[1],
                    'file' => $r->getFileName(),
                    'line' => $r->getStartLine(),
                );
            } catch (\ReflectionException $e) {
                if (is_callable($controller)) {
                    // using __call or  __callStatic
                    return array(
                        'class' => is_object($controller[0]) ? get_class($controller[0]) : $controller[0],
                        'method' => $controller[1],
                        'file' => 'n/a',
                        'line' => 'n/a',
                    );
                }
            }
        }

        if ($controller instanceof \Closure) {
            $r = new \ReflectionFunction($controller);

            return array(
                'class' => $r->getName(),
                'method' => null,
                'file' => $r->getFileName(),
                'line' => $r->getStartLine(),
            );
        }

        if (is_object($controller)) {
            $r = new \ReflectionClass($controller);

            return array(
                'class' => $r->getName(),
                'method' => null,
                'file' => $r->getFileName(),
                'line' => $r->getStartLine(),
            );
        }

        return is_string($controller) ? $controller : 'n/a';
    }
}
