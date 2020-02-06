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
     * @var callable[]
     */
    protected $listeners = [];

    /**
     * @var callable[]
     */
    protected $sharedListeners = [];

    /**
     * @param EventManagerInterface $events
     * @param int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('dispatch', [$this, 'onDispatch'], 100);
    }

    /**
     * @param EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    /**
     * @param SharedEventManagerInterface $events
     *
     * @return void
     */
    public function attachShared(SharedEventManagerInterface $events)
    {
        $this->sharedListeners[] = $events->attach(ResourceController::class, 'dispatch', [$this, 'onDispatch'], 100);
    }

    /**
     * @param SharedEventManagerInterface $events
     *
     * @return void
     */
    public function detachShared(SharedEventManagerInterface $events)
    {
        // Vary detachment based on zend-eventmanager version.
        $detach = method_exists($events, 'attachAggregate')
            ? /**
             * @param callable $listener
             * @return bool
             */
            function ($listener) use ($events) {
                return $events->detach(ResourceController::class, $listener);
            }
        : /**
         * @param callable $listener
         * @return bool
         */
        function ($listener) use ($events) {
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
     *
     * @return void
     */
    public function onDispatch(MvcEvent $e)
    {
        $controller = $e->getTarget();
        if (!$controller instanceof ResourceController) {
            return;
        }

        /** @var \Zend\Http\Request $request */
        $request  = $e->getRequest();
        $query    = $request->getQuery();
        $matches  = $e->getRouteMatch();
        $resource = $controller->getResource();
        $resource->setQueryParams($query);
        $resource->setRouteMatch($matches);
    }
}
