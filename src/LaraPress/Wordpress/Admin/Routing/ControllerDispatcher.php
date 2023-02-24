<?php

namespace LaraPress\Wordpress\Admin\Routing;

use LaraPress\Container\Container;
use LaraPress\Routing\RouteDependencyResolverTrait;

class ControllerDispatcher
{
    use RouteDependencyResolverTrait;

    /**
     * The container instance.
     *
     * @var \LaraPress\Container\Container
     */
    protected $container;

    /**
     * Create a new controller dispatcher instance.
     *
     * @param \LaraPress\Container\Container $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given controller and method.
     *
     * @param Menu $menu
     * @param mixed $controller
     * @param string $method
     * @return mixed
     */
    public function dispatch(Menu $menu, $controller, string $method)
    {
        $parameters = $this->resolveClassMethodDependencies(
            [], $controller, $method
        );
        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }

        return $controller->{$method}(...array_values($parameters));
    }

    /**
     * Get the middleware for the controller instance.
     *
     * @param \LaraPress\Routing\Controller $controller
     * @param string $method
     * @return array
     */
    public function getMiddleware($controller, $method)
    {
        if (!method_exists($controller, 'getMiddleware')) {
            return [];
        }

        return lp_collect($controller->getMiddleware())->reject(function ($data) use ($method) {
            return static::methodExcludedByOptions($method, $data['options']);
        })->pluck('middleware')->all();
    }

    /**
     * Determine if the given options exclude a particular method.
     *
     * @param string $method
     * @param array $options
     * @return bool
     */
    protected static function methodExcludedByOptions($method, array $options)
    {
        return (isset($options['only']) && !in_array($method, (array)$options['only'])) ||
            (!empty($options['except']) && in_array($method, (array)$options['except']));
    }

}