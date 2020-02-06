<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\TestAsset;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

class CollectionIntegrationListener implements ListenerAggregateInterface
{
    public $collection;

    protected $listeners = [];

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('fetchAll', [$this, 'onFetchAll']);
    }

    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    public function setCollection($collection)
    {
        $this->collection = $collection;
    }

    public function onFetchAll($e)
    {
        return $this->collection;
    }
}
