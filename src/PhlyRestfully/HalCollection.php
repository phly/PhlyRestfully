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
     * @var array|Traversable|\Zend\Paginator\Paginator
     */
    protected $collection;

    /**
     * @var string
     */
    protected $collectionRoute;

    /**
     * @var string
     */
    protected $itemRoute;

    /**
     * Current page
     *
     * @var int
     */
    protected $page = 1;

    /**
     * Number of items per page
     *
     * @var int
     */
    protected $pageSize = 30;

    /**
     * @param  array|Traversable|\Zend\Paginator\Paginator $collection
     * @param  string $collectionRoute
     * @param  string $itemRoute
     * @throws Exception\InvalidCollectionException
     */
    public function __construct($collection, $collectionRoute, $itemRoute)
    {
        if (!is_array($collection) && !$collection instanceof Traversable) {
            throw new Exception\InvalidCollectionException();
        }

        $this->collection      = $collection;
        $this->collectionRoute = (string) $collectionRoute;
        $this->itemRoute       = (string) $itemRoute;
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
            'collection'       => 'collection',
            'collectionroute'  => 'collectionRoute',
            'collection_route' => 'collectionRoute',
            'itemroute'        => 'itemRoute',
            'item_route'       => 'itemRoute',
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
}
