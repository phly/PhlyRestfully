<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\Stdlib\Hydrator\HydratorPluginManager;

class Metadata
{
    /**
     * Class this metadata applies to
     *
     * @var string
     */
    protected $class;

    /**
     * Hydrator to use when extracting object of this class
     *
     * @var HydratorInterface
     */
    protected $hydrator;

    /**
     * @var HydratorPluginManager
     */
    protected $hydrators;

    /**
     * Name of the field representing the identifier
     *
     * @var string
     */
    protected $identifierName = 'id';

    /**
     * Does the class represent a collection?
     *
     * @var bool
     */
    protected $isCollection = false;

    /**
     * Route for resources composed in a collection
     *
     * @var string
     */
    protected $resourceRoute;

    /**
     * Route to use to generate a self link for this resource
     *
     * @var string
     */
    protected $route;

    /**
     * Additional options to use when generating a self link for this resource
     *
     * @var array
     */
    protected $routeOptions = array();

    /**
     * Additional route parameters to use when generating a self link for this resource
     *
     * @var array
     */
    protected $routeParams = array();

    /**
     * URL to use for this resource (instead of a route)
     *
     * @var string
     */
    protected $url;

    /**
     * Constructor
     *
     * Sets the class, and passes any options provided to the appropriate
     * setter methods, after first converting them to lowercase and stripping
     * underscores.
     *
     * If the class does not exist, raises an exception.
     *
     * @param  string $class
     * @param  array $options
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($class, array $options = array(), HydratorPluginManager $hydrators = null)
    {
        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Class provided to %s must exist; received "%s"',
                __CLASS__,
                $class
            ));
        }
        $this->class = $class;

        if (null !== $hydrators) {
            $this->hydrators = $hydrators;
        }

        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $key = str_replace('_', '', $key);

            if ('class' == $key) {
                continue;
            }

            $method = 'set' . $key;
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Retrieve the class this metadata is associated with
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Retrieve the hydrator to associate with this class, if any
     *
     * @return null|HydratorInterface
     */
    public function getHydrator()
    {
        return $this->hydrator;
    }

    /**
     * Retrieve the identifier name
     *
     * @return string
     */
    public function getIdentifierName()
    {
        return $this->identifierName;
    }

    /**
     * Retrieve the resource route
     *
     * If not set, uses the route or url, depending on which is present.
     *
     * @return null|string
     */
    public function getResourceRoute()
    {
        if (null === $this->resourceRoute) {
            if ($this->hasRoute()) {
                $this->setResourceRoute($this->getRoute());
            } else {
                $this->setResourceRoute($this->getUrl());
            }
        }
        return $this->resourceRoute;
    }

    /**
     * Retrieve the route to use for URL generation
     *
     * @return null|string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Retrieve an route options to use in URL generation
     *
     * @return array
     */
    public function getRouteOptions()
    {
        return $this->routeOptions;
    }

    /**
     * Retrieve any route parameters to use in URL generation
     *
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Retrieve the URL to use for this resource, if present
     *
     * @return null|string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Is a hydrator associated with this class?
     *
     * @return bool
     */
    public function hasHydrator()
    {
        return (null !== $this->hydrator);
    }

    /**
     * Is a route present for this class?
     *
     * @return bool
     */
    public function hasRoute()
    {
        return (null !== $this->route);
    }

    /**
     * Is a URL set for this class?
     *
     * @return bool
     */
    public function hasUrl()
    {
        return (null !== $this->url);
    }

    /**
     * Does this class represent a collection?
     *
     * @return bool
     */
    public function isCollection()
    {
        return $this->isCollection;
    }

    /**
     * Set the hydrator to use with this class
     *
     * @param  string|HydratorInterface $hydrator
     * @return self
     * @throws Exception\InvalidArgumentException if the class or hydrator does not implement HydratorInterface
     */
    public function setHydrator($hydrator)
    {
        if (is_string($hydrator)) {
            if (null !== $this->hydrators
                && $this->hydrators->has($hydrator)
            ) {
                $hydrator = $this->hydrators->get($hydrator);
            } elseif (class_exists($hydrator)) {
                $hydrator = new $hydrator();
            }
        }
        if (!$hydrator instanceof HydratorInterface) {
            if (is_object($hydrator)) {
                $type = get_class($hydrator);
            } elseif (is_string($hydrator)) {
                $type = $hydrator;
            } else {
                $type = gettype($hydrator);
            }
            throw new Exception\InvalidArgumentException(sprintf(
                'Hydrator class must implement Zend\Stdlib\Hydrator\Hydrator; received "%s"',
                $type
            ));
        }
        $this->hydrator = $hydrator;
        return $this;
    }

    /**
     * Set the identifier name
     *
     * @param  string|mixed $identifier
     * @return self
     */
    public function setIdentifierName($identifier)
    {
        $this->identifierName = $identifier;
        return $this;
    }

    /**
     * Set the flag indicating collection status
     *
     * @param  bool $flag
     * @return self
     */
    public function setIsCollection($flag)
    {
        $this->isCollection = (bool) $flag;
        return $this;
    }

    /**
     * Set the resource route (for embedded resources in collections)
     *
     * @param  string $route
     * @return self
     */
    public function setResourceRoute($route)
    {
        $this->resourceRoute = $route;
        return $this;
    }

    /**
     * Set the route for URL generation
     *
     * @param  string $route
     * @return self
     */
    public function setRoute($route)
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Set route options for URL generation
     *
     * @param  array $options
     * @return self
     */
    public function setRouteOptions(array $options)
    {
        $this->routeOptions = $options;
        return $this;
    }

    /**
     * Set route parameters for URL generation
     *
     * @param  array $params
     * @return self
     */
    public function setRouteParams(array $params)
    {
        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set the URL to use with this resource
     *
     * @param  string $url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }
}
