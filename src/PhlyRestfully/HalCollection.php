<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Traversable;

/**
 * Model a collection for use with HAL payloads
 */
class HalCollection
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
     * @var string
     */
    protected $resourceRoute;

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
     * @param  array|Traversable|\Zend\Paginator\Paginator $collection
     * @param  string $collectionRoute
     * @param  string $resourceRoute
     * @throws Exception\InvalidCollectionException
     */
    public function __construct($collection, $collectionRoute = null, $resourceRoute = null)
    {
        if (!is_array($collection) && !$collection instanceof Traversable) {
            throw new Exception\InvalidCollectionException();
        }

        $this->collection = $collection;
        if (null !== $collectionRoute) {
            $this->setCollectionRoute($collectionRoute);
        }
        if (null !== $resourceRoute) {
            $this->setResourceRoute($resourceRoute);
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
            'attributes'       => 'attributes',
            'collection'       => 'collection',
            'collectionname'   => 'collectionName',
            'collection_name'  => 'collectionName',
            'collectionroute'  => 'collectionRoute',
            'collection_route' => 'collectionRoute',
            'resourceroute'    => 'resourceRoute',
            'resource_route'   => 'resourceRoute',
            'page'             => 'page',
            'pagesize'         => 'pageSize',
            'page_size'        => 'pageSize',
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
     * Set the collection route
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
}
