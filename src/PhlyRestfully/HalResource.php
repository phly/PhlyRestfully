<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

class HalResource implements LinkCollectionAwareInterface
{
    protected $id;

    /**
     * @var LinkCollection
     */
    protected $links;

    protected $resource;

    protected $route;

    protected $routeParams;

    /**
     * @param  object|array $resource
     * @param  mixed $id
     * @param  string $route
     * @param  array $routeParams
     * @throws Exception\InvalidResourceException if resource is not an object or array
     */
    public function __construct($resource, $id, $route = null, array $routeParams = array())
    {
        if (!is_object($resource) && !is_array($resource)) {
            throw new Exception\InvalidResourceException();
        }

        $this->resource    = $resource;
        $this->id          = $id;
        $this->route       = (string) $route;
        $this->routeParams = $routeParams;
    }

    /**
     * Retrieve properties
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $names = array(
            'resource'     => 'resource',
            'id'           => 'id',
            'route'        => 'route',
            'routeparams'  => 'routeParams',
            'route_params' => 'routeParams',
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

    public function __set($name, $value)
    {
        $names = array(
            'route'        => 'route',
            'routeparams'  => 'routeParams',
            'route_params' => 'routeParams',
        );
        $name = strtolower($name);
        if (!in_array($name, array_keys($names))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Cannot set property name "%s"',
                $name
            ));
        }
        $prop = $names[$name];
        $this->{$prop} = $value;
        return true;
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
}
