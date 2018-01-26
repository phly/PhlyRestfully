<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Listener;

use PhlyRestfully\ResourceController;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;

class ResourceParametersListener implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = [];

    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $sharedListeners = [];

    /**
     * @param EventManagerInterface $events
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('dispatch', [$this, 'onDispatch'], 100);
    }

    /**
     * @param EventManagerInterface $events
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * @param SharedEventManagerInterface $events
     */
    public function attachShared(SharedEventManagerInterface $events)
    {
        $this->sharedListeners[] = $events->attach(ResourceController::class, 'dispatch', [$this, 'onDispatch'], 100);
    }

    /**
     * @param SharedEventManagerInterface $events
     */
    public function detachShared(SharedEventManagerInterface $events)
    {
        // Vary detachment based on zend-eventmanager version.
        $detach = method_exists($events, 'attachAggregate')
            ? function ($listener) use ($events) {
                return $events->detach(ResourceController::class, $listener);
            }
        : function ($listener) use ($events) {
            return $events->detach($listener, ResourceController::class);
        };

        foreach ($this->sharedListeners as $index => $listener) {
            if ($detach($listener)) {
                unset($this->sharedListeners[$index]);
            }
        }
    }

    /**
     * Listen to the dispatch event
     *
     * @param MvcEvent $e
     */
    public function onDispatch(MvcEvent $e)
    {
        $controller = $e->getTarget();
        if (!$controller instanceof ResourceController) {
            return;
        }

        $request  = $e->getRequest();
        $query    = $request->getQuery();
        $matches  = $e->getRouteMatch();
        $resource = $controller->getResource();
        $resource->setQueryParams($query);
        $resource->setRouteMatch($matches);
    }
}
