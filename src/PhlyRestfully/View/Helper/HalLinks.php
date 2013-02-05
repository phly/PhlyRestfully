<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\View\Helper;

use ArrayObject;
use PhlyRestfully\ApiProblem;
use PhlyRestfully\Exception;
use PhlyRestfully\HalCollection;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Zend\Paginator\Paginator;
use Zend\Stdlib\DispatchableInterface;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;

/**
 * Generate links for use with HAL payloads
 */
class HalLinks extends AbstractHelper implements ControllerPluginInterface
{
    /**
     * @var DispatchableInterface
     */
    protected $controller;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var ServerUrl
     */
    protected $serverUrlHelper;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @param DispatchableInterface $controller 
     */
    public function setController(DispatchableInterface $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return DispatchableInterface
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Retrieve the event manager instance
     *
     * Lazy-initializes one if none present.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Set the event manager instance
     *
     * @param  EventManagerInterface $events
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_class($this),
        ));
        $this->events = $events;
    }

    /**
     * @param ServerUrl $helper 
     */
    public function setServerUrlHelper(ServerUrl $helper)
    {
        $this->serverUrlHelper = $helper;
    }

    /**
     * @param Url $helper 
     */
    public function setUrlHelper(Url $helper)
    {
        $this->urlHelper = $helper;
    }

    /**
     * Create a fully qualified URI for a link
     *
     * Triggers the "createLink" event with the route, id, item, and a set of
     * params that will be passed to the route; listeners can alter any of the
     * arguments, which will then be used by the method to generate the url.
     *
     * @param  string $route
     * @param  null|false|int|string $id
     * @param  null|mixed $item
     * @return string
     */
    public function createLink($route, $id = null, $item = null)
    {
        $params             = new ArrayObject();
        $reUseMatchedParams = true;

        if (false === $id) {
            $reUseMatchedParams = false;
        } elseif (null !== $id) {
            $params['id'] = $id;
        }

        $events      = $this->getEventManager();
        $eventParams = $events->prepareArgs(array(
            'route'  => $route,
            'id'     => $id,
            'item'   => $item,
            'params' => $params,
        ));
        $events->trigger(__FUNCTION__, $this, $eventParams);
        $route = $eventParams['route'];

        $path = call_user_func($this->urlHelper, $route, $params->getArrayCopy(), $reUseMatchedParams);
        return call_user_func($this->serverUrlHelper, $path);
    }

    /**
     * Generate HAL links for a given item
     *
     * Generates a "self" link
     * 
     * @param  string $route 
     * @param  null|false|mixed $id 
     * @param  array|object $item 
     * @return array
     */
    public function forItem($route, $id = null, $item = null)
    {
        $url = $this->createLink($route, $id, $item);
        return array('self' => array('href' => $url));
    }

    /**
     * Generate a self link for a collection
     * 
     * @param  string $route 
     * @param  null|false $reUseMatchedParams
     * @return array
     */
    public function forCollection($route, $reUseMatchedParams = null)
    {
        $url  = $this->createLink($route, $reUseMatchedParams);
        return array('self' => array('href' => $url));
    }

    /**
     * Generate HAL links for a paginated collection
     * 
     * @param  HalCollection $halCollection 
     * @return array
     */
    public function forPaginatedCollection(HalCollection $halCollection)
    {
        $collection = $halCollection->collection;
        if (!$collection instanceof Paginator) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid collection provided: must be a Paginator instance; received "%s"',
                get_class($collection)
            ));
        }

        $page     = $halCollection->page;
        $pageSize = $halCollection->pageSize;
        $route    = $halCollection->collectionRoute;

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);

        $count    = count($collection);
        if (!$count) {
            return $this->forCollection($route);
        }

        if ($page < 1 || $page > $count) {
            return new ApiProblem(409, 'Invalid page provided');
        }

        $path = call_user_func($this->urlHelper, $route);
        $base = call_user_func($this->serverUrlHelper, $path);

        $next  = ($page == $count) ? false : $page + 1;
        $prev  = ($page == 1) ? false : $page - 1;
        $links = array(
            'self'  => $base . ((1 == $page) ? '' : '?page=' . $page),
        );
        if ($page != 1) {
            $links['first'] = $base;
        }
        if ($count != 1) {
            $links['last'] = $base . '?page=' . $count;
        }
        if ($prev) {
            $links['prev'] = $base . ((1 == $prev) ? '' : '?page=' . $prev);
        }
        if ($next) {
            $links['next'] = $base . '?page=' . $next;
        }

        foreach ($links as $index => $link) {
            $links[$index] = array('href' => $link);
        }

        return $links;
    }
}
