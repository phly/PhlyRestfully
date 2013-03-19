<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Traversable;
use Zend\Stdlib\ArrayUtils;

/**
 * Model a collection for use with HAL payloads
 */
class HalCollection implements LinkCollectionAwareInterface
{
    /**
     * Additional attributes to render with resource
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * @var array|Traversable|\Zend\Paginator\Paginator
     */
    protected $collection;

    /**
     * Name of collection (used to identify it in the "_embedded" object)
     *
     * @var string
     */
    protected $collectionName = 'items';

    /**
     * @var string
     */
    protected $collectionRoute;

    /**
     * @var array
     */
    protected $collectionRouteOptions = array();

    /**
     * @var array
     */
    protected $collectionRouteParams = array();

    /**
     * @var LinkCollection
     */
    protected $links;

    /**
     * Current page
     *
     * @var int
     */
    protected $page = 1;

    /**
     * Number of resources per page
     *
     * @var int
     */
    protected $pageSize = 30;

    /**
     * @var LinkCollection
     */
    protected $resourceLinks;

    /**
     * @var string
     */
    protected $resourceRoute;

    /**
     * @var array
     */
    protected $resourceRouteOptions = array();

    /**
     * @var array
     */
    protected $resourceRouteParams = array();

    /**
     * @param  array|Traversable|\Zend\Paginator\Paginator $collection
     * @param  string $collectionRoute
     * @param  string $resourceRoute
     * @throws Exception\InvalidCollectionException
     */
    public function __construct($collection, $resourceRoute = null, $resourceRouteParams = null, $resourceRouteOptions = null)
    {
        if (!is_array($collection) && !$collection instanceof Traversable) {
            throw new Exception\InvalidCollectionException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($collection) ? get_class($collection) : gettype($collection))
            ));
        }

        $this->collection = $collection;

        if (null !== $resourceRoute) {
            $this->setResourceRoute($resourceRoute);
        }
        if (null !== $resourceRouteParams) {
            $this->setResourceRouteParams($resourceRouteParams);
        }
        if (null !== $resourceRouteOptions) {
            $this->setResourceRouteOptions($resourceRouteOptions);
        }
    }

    /**
     * Proxy to properties to allow read access
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $names = array(
            'attributes'               => 'attributes',
            'collection'               => 'collection',
            'collectionname'           => 'collectionName',
            'collection_name'          => 'collectionName',
            'collectionroute'          => 'collectionRoute',
            'collection_route'         => 'collectionRoute',
            'collectionrouteoptions'   => 'collectionRouteOptions',
            'collection_route_options' => 'collectionRouteOptions',
            'collectionrouteparams'    => 'collectionRouteParams',
            'collection_route_params'  => 'collectionRouteParams',
            'links'                    => 'links',
            'resourcelinks'            => 'resourceLinks',
            'resource_links'           => 'resourceLinks',
            'resourceroute'            => 'resourceRoute',
            'resource_route'           => 'resourceRoute',
            'resourcerouteoptions'     => 'resourceRouteOptions',
            'resource_route_options'   => 'resourceRouteOptions',
            'resourcerouteparams'      => 'resourceRouteParams',
            'resource_route_params'    => 'resourceRouteParams',
            'page'                     => 'page',
            'pagesize'                 => 'pageSize',
            'page_size'                => 'pageSize',
        );
        $name = strtolower($name);
        if (!in_array($name, array_keys($names))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid property name "%s"',
                $name
            ));
        }
        $prop = $names[$name];
        return $this->{$prop};
    }

    /**
     * Set additional attributes to render as part of resource
     *
     * @param  array $attributes
     * @return HalCollection
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Set the collection name (for use within the _embedded object)
     *
     * @param  string $name
     * @return HalCollection
     */
    public function setCollectionName($name)
    {
        $this->collectionName = (string) $name;
        return $this;
    }

    /**
     * Set the collection route; used for generating pagination links
     *
     * @param  string $route
     * @return HalCollection
     */
    public function setCollectionRoute($route)
    {
        $this->collectionRoute = (string) $route;
        return $this;
    }

    /**
     * Set options to use with the collection route; used for generating pagination links
     *
     * @param  array|Traversable $options
     * @return HalCollection
     * @throws Exception\InvalidArgumentException
     */
    public function setCollectionRouteOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }
        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($options) ? get_class($options) : gettype($options))
            ));
        }
        $this->collectionRouteOptions = $options;
        return $this;
    }

    /**
     * Set parameters/substitutions to use with the collection route; used for generating pagination links
     *
     * @param  array|Traversable $params
     * @return HalCollection
     * @throws Exception\InvalidArgumentException
     */
    public function setCollectionRouteParams($params)
    {
        if ($params instanceof Traversable) {
            $params = ArrayUtils::iteratorToArray($params);
        }
        if (!is_array($params)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }
        $this->collectionRouteParams = $params;
        return $this;
    }

    /**
     * Set link collection
     *
     * @param  LinkCollection $links
     * @return self
     */
    public function setLinks(LinkCollection $links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Set current page
     *
     * @param  int $page
     * @return HalCollection
     * @throws Exception\InvalidArgumentException for non-positive and/or non-integer values
     */
    public function setPage($page)
    {
        if (!is_int($page) && !is_numeric($page)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Page must be an integer; received "%s"',
                gettype($page)
            ));
        }

        $page = (int) $page;
        if ($page < 1) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Page must be a positive integer; received "%s"',
                $page
            ));
        }

        $this->page = $page;
        return $this;
    }

    /**
     * Set page size
     *
     * @param  int $size
     * @return HalCollection
     * @throws Exception\InvalidArgumentException for non-positive and/or non-integer values
     */
    public function setPageSize($size)
    {
        if (!is_int($size) && !is_numeric($size)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Page size must be an integer; received "%s"',
                gettype($size)
            ));
        }

        $size = (int) $size;
        if ($size < 1) {
            throw new Exception\InvalidArgumentException(sprintf(
                'size must be a positive integer; received "%s"',
                $size
            ));
        }

        $this->pageSize = $size;
        return $this;
    }

    /**
     * Set default set of links to use for resources
     *
     * @param  LinkCollection $links
     * @return self
     */
    public function setResourceLinks(LinkCollection $links)
    {
        $this->resourceLinks = $links;
        return $this;
    }

    /**
     * Set the resource route
     *
     * @param  string $route
     * @return HalCollection
     */
    public function setResourceRoute($route)
    {
        $this->resourceRoute = (string) $route;
        return $this;
    }

    /**
     * Set options to use with the resource route
     *
     * @param  array|Traversable $options
     * @return HalCollection
     * @throws Exception\InvalidArgumentException
     */
    public function setResourceRouteOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }
        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($options) ? get_class($options) : gettype($options))
            ));
        }
        $this->resourceRouteOptions = $options;
        return $this;
    }

    /**
     * Set parameters/substitutions to use with the resource route
     *
     * @param  array|Traversable $params
     * @return HalCollection
     * @throws Exception\InvalidArgumentException
     */
    public function setResourceRouteParams($params)
    {
        if ($params instanceof Traversable) {
            $params = ArrayUtils::iteratorToArray($params);
        }
        if (!is_array($params)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }
        $this->resourceRouteParams = $params;
        return $this;
    }

    /**
     * Get link collection
     *
     * @return LinkCollection
     */
    public function getLinks()
    {
        if (!$this->links instanceof LinkCollection) {
            $this->setLinks(new LinkCollection());
        }
        return $this->links;
    }

    /**
     * Retrieve default resource links, if any
     *
     * @return null|LinkCollection
     */
    public function getResourceLinks()
    {
        return $this->resourceLinks;
    }
}
