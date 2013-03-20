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
use PhlyRestfully\HalResource;
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
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;

/**
 * Generate links for use with HAL payloads
 */
class HalLinks extends AbstractHelper implements
    ControllerPluginInterface,
    EventManagerAwareInterface
{
    /**
     * @var DispatchableInterface
     */
    protected $controller;

    /**
     * Default hydrator to use if no hydrator found for a specific resource class.
     *
     * @var HydratorInterface
     */
    protected $defaultHydrator;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * Map of resource classes => hydrators
     *
     * @var HydratorInterface[]
     */
    protected $hydrators = array();

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

        $events->attach('getIdFromResource', function ($e) {
            $resource = $e->getParam('resource');

            if (!is_array($resource)) {
                return false;
            }

            if (array_key_exists('id', $resource)) {
                return $resource['id'];
            }

            return false;
        });

        return $this;
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
     * Map a resource class to a specific hydrator instance
     *
     * @param  string $class
     * @param  HydratorInterface $hydrator
     * @return RestfulJsonRenderer
     */
    public function addHydrator($class, HydratorInterface $hydrator)
    {
        $this->hydrators[strtolower($class)] = $hydrator;
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     * @param  HydratorInterface $hydrator
     * @return RestfulJsonRenderer
     */
    public function setDefaultHydrator(HydratorInterface $hydrator)
    {
        $this->defaultHydrator = $hydrator;
        return $this;
    }

    /**
     * Retrieve a hydrator for a given resource
     *
     * If the resource has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param  object $resource
     * @return HydratorInterface|false
     */
    public function getHydratorForResource($resource)
    {
        $class = strtolower(get_class($resource));
        if (isset($this->hydrators[$class])) {
            return $this->hydrators[$class];
        }

        if ($this->defaultHydrator instanceof HydratorInterface) {
            return $this->defaultHydrator;
        }

        return false;
    }

    /**
     * "Render" a HalCollection
     *
     * Injects pagination links, if the composed collection is a Paginator, and
     * then loops through the collection to create the data structure representing
     * the collection.
     *
     * @param  HalCollection $halCollection
     * @return array|ApiProblem Associative array representing the payload to render; returns ApiProblem if error in pagination occurs
     */
    public function renderCollection(HalCollection $halCollection)
    {
        $collection     = $halCollection->collection;
        $collectionName = $halCollection->collectionName;

        if ($collection instanceof Paginator) {
            $status = $this->injectPaginationLinks($halCollection);
            if ($status instanceof ApiProblem) {
                return $status;
            }
        }

        $payload = $halCollection->attributes;
        $payload['_links']    = $this->fromResource($halCollection);
        $payload['_embedded'] = array(
            $collectionName => array(),
        );

        $resourceRoute        = $halCollection->resourceRoute;
        $resourceRouteParams  = $halCollection->resourceRouteParams;
        $resourceRouteOptions = $halCollection->resourceRouteOptions;
        foreach ($collection as $resource) {
            $origResource = $resource;
            if (!is_array($resource)) {
                $resource = $this->convertResourceToArray($resource);
            }

            foreach ($resource as $key => $value) {
                if (!$value instanceof HalResource) {
                    continue;
                }
                $this->extractEmbeddedHalResource($resource, $key, $value);
            }

            $id = $this->getIdFromResource($resource);
            if (!$id) {
                // Cannot handle resources without an identifier
                // Return as-is
                $payload['_embedded'][$collectionName][] = $resource;
                continue;
            }

            $link = new Link('self');
            $link->setRoute(
                $resourceRoute,
                array_merge($resourceRouteParams, array('id' => $id)),
                $resourceRouteOptions
            );
            $links = new LinkCollection();
            $links->add($link);

            $resource['_links'] = $this->fromLinkCollection($links);
            $payload['_embedded'][$collectionName][] = $resource;
        }

        return $payload;
    }

    public function renderResource(HalResource $halResource)
    {
        $resource = $halResource->resource;
        $id       = $halResource->id;
        $links    = $this->fromResource($halResource);

        if (!is_array($resource)) {
            $resource = $this->convertResourceToArray($resource);
        }

        foreach ($resource as $key => $value) {
            if (!$value instanceof HalResource) {
                continue;
            }
            $this->extractEmbeddedHalResource($resource, $key, $value);
        }

        $resource['_links'] = $links;

        return $resource;
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
    protected function injectPaginationLinks(HalCollection $halCollection)
    {
        $collection = $halCollection->collection;
        $page       = $halCollection->page;
        $pageSize   = $halCollection->pageSize;
        $route      = $halCollection->collectionRoute;
        $params     = $halCollection->collectionRouteParams;
        $options    = $halCollection->collectionRouteOptions;

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);

        $count = count($collection);
        if (!$count) {
            return true;
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

        return true;
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

    /**
     * Extracts and renders a HalResource and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     *
     * @param  array $parent
     * @param  string $key
     * @param  HalResource $resource
     */
    protected function extractEmbeddedHalResource(array &$parent, $key, HalResource $resource)
    {
        $rendered = $this->renderResource($resource);
        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = array();
        }
        $parent['_embedded'][$key] = $rendered;
        unset($parent[$key]);
    }

    /**
     * Retrieve the identifier from a resource
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     *
     * Triggers the "getIdFromResource" event with the resource; listeners can
     * return a non-false, non-null value in order to specify the identifier
     * to use for URL assembly.
     *
     * @param  array $resource
     * @return mixed|false
     */
    protected function getIdFromResource(array $resource)
    {
        $results = $this->getEventManager()->trigger(
            __FUNCTION__,
            $this,
            array('resource' => $resource),
            function ($r) {
                return (null !== $r && false !== $r);
            }
        );

        if ($results->stopped()) {
            return $results->last();
        }

        return false;
    }

    /**
     * Convert an individual resource to an array
     *
     * @param  object $resource
     * @return array
     */
    protected function convertResourceToArray($resource)
    {
        $hydrator = $this->getHydratorForResource($resource);
        if (!$hydrator) {
            return (array) $resource;
        }

        return $hydrator->extract($resource);
    }

}
