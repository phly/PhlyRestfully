<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Plugin;

use ArrayObject;
use PhlyRestfully\ApiProblem;
use PhlyRestfully\Exception;
use PhlyRestfully\HalCollection;
use PhlyRestfully\Link;
use PhlyRestfully\LinkCollection;
use PhlyRestfully\LinkCollectionAwareInterface;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Zend\Paginator\Paginator;
use Zend\Stdlib\ArrayUtils;
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
     * Triggers the "createLink" event with the route, id, resource, and a set of
     * params that will be passed to the route; listeners can alter any of the
     * arguments, which will then be used by the method to generate the url.
     *
     * @param  string $route
     * @param  null|false|int|string $id
     * @param  null|mixed $resource
     * @return string
     */
    public function createLink($route, $id = null, $resource = null)
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
            'route'    => $route,
            'id'       => $id,
            'resource' => $resource,
            'params'   => $params,
        ));
        $events->trigger(__FUNCTION__, $this, $eventParams);
        $route = $eventParams['route'];

        $path = call_user_func($this->urlHelper, $route, $params->getArrayCopy(), $reUseMatchedParams);
        return call_user_func($this->serverUrlHelper, $path);
    }

    /**
     * Generate HAL links for a given resource
     *
     * Generates a "self" link
     *
     * @param  string $route
     * @param  null|false|mixed $id
     * @param  array|object $resource
     * @return array
     */
    public function forResource($route, $id = null, $resource = null)
    {
        $url = $this->createLink($route, $id, $resource);
        return array('self' => array('href' => $url));
    }

    /**
     * Generate HAL links from a LinkCollection
     * 
     * @param  LinkCollection $collection 
     * @return array
     */
    public function fromLinkCollection(LinkCollection $collection)
    {
        $links = array();
        foreach($collection as $rel => $linkDefinition) {
            if ($linkDefinition instanceof Link) {
                $links[$rel] = $this->fromLink($linkDefinition);
                continue;
            }
            if (!is_array($linkDefinition)) {
                throw new Exception\DomainException(sprintf(
                    'Link object for relation "%s" in resource was malformed; cannot generate link',
                    $rel
                ));
            }

            $aggregate = array();
            foreach ($linkDefinition as $subLink) {
                if (!$subLink instanceof Link) {
                    throw new Exception\DomainException(sprintf(
                        'Link object aggregated for relation "%s" in resource was malformed; cannot generate link',
                        $rel
                    ));
                }
                $aggregate[] = $this->fromLink($subLink);
            }
            $links[$rel] = $aggregate;
        }
        return $links;
    }

    /**
     * Create HAL links "object" from a resource/collection
     * 
     * @param  LinkCollectionAwareInterface $resource 
     * @return array
     */
    public function fromResource(LinkCollectionAwareInterface $resource)
    {
        return $this->fromLinkCollection($resource->getLinks());
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
        $params   = $halCollection->collectionRouteParams;
        $options  = $halCollection->collectionRouteOptions;

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);

        $count = count($collection);
        if (!$count) {
            return $this->fromResource($halCollection);
        }

        if ($page < 1 || $page > $count) {
            return new ApiProblem(409, 'Invalid page provided');
        }

        $links = $halCollection->getLinks();
        $next  = ($page < $count) ? $page + 1 : false;
        $prev  = ($page > 1)      ? $page - 1 : false;

        // self link
        $link = new Link('self');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions(ArrayUtils::merge($options, array(
            'query' => array('page' => $page))
        ));
        $links->add($link, true);

        // first link
        $link = new Link('first');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions($options);
        $links->add($link);

        // last link
        $link = new Link('last');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions(ArrayUtils::merge($options, array(
            'query' => array('page' => $count))
        ));
        $links->add($link);

        // prev link
        if ($prev) {
            $link = new Link('prev');
            $link->setRoute($route);
            $link->setRouteParams($params);
            $link->setRouteOptions(ArrayUtils::merge($options, array(
                'query' => array('page' => $prev))
            ));
            $links->add($link);
        }

        // next link
        if ($next) {
            $link = new Link('next');
            $link->setRoute($route);
            $link->setRouteParams($params);
            $link->setRouteOptions(ArrayUtils::merge($options, array(
                'query' => array('page' => $next))
            ));
            $links->add($link);
        }

        return $this->fromResource($halCollection);
    }

    /**
     * Create a URL from a Link
     * 
     * @param  Link $linkDefinition 
     * @return string
     * @throws Exception\DomainException if Link is incomplete
     */
    protected function fromLink(Link $linkDefinition)
    {
        if (!$linkDefinition->isComplete()) {
            throw new Exception\DomainException(sprintf(
                'Link from resource provided to %s was incomplete; must contain a URL or a route',
                __METHOD__
            ));
        }

        if ($linkDefinition->hasUrl()) {
            return array(
                'href' => $linkDefinition->getUrl(),
            );
        }

        $path = call_user_func(
            $this->urlHelper,
            $linkDefinition->getRoute(),
            $linkDefinition->getRouteParams(),
            $linkDefinition->getRouteOptions()
        );
        return array(
            'href' => call_user_func($this->serverUrlHelper, $path),
        );
    }
}
