<?php

namespace PhlyRestfully;

use Zend\Stdlib\Hydrator\HydratorInterface;

class Metadata
{
    protected $class;

    protected $hydrator;

    protected $identifierName = 'id';

    protected $isCollection = false;

    protected $resourceRoute;

    protected $route;

    protected $routeOptions = array();

    protected $routeParams = array();

    protected $url;

    public function __construct($class, array $options = array())
    {
        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Class provided to %s must exist; received "%s"',
                __CLASS__,
                $class
            ));
        }
        $this->class = $class;

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

    public function getClass()
    {
        return $this->class;
    }

    public function getHydrator()
    {
        return $this->hydrator;
    }

    public function getIdentifierName()
    {
        return $this->identifierName;
    }

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

    public function getRoute()
    {
        return $this->route;
    }

    public function getRouteOptions()
    {
        return $this->routeOptions;
    }

    public function getRouteParams()
    {
        return $this->routeParams;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function hasHydrator()
    {
        return (null !== $this->hydrator);
    }

    public function hasRoute()
    {
        return (null !== $this->route);
    }

    public function hasUrl()
    {
        return (null !== $this->url);
    }

    public function isCollection()
    {
        return $this->isCollection;
    }

    public function setHydrator($hydrator)
    {
        if (is_string($hydrator)) {
            if (!class_exists($hydrator)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Hydrator class must exist; received "%s"',
                    $hydrator
                ));
            }
            $hydrator = new $hydrator();
        }
        if (!$hydrator instanceof HydratorInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Hydrator class must implement Zend\Stdlib\Hydrator\Hydrator; received "%s"',
                $class
            ));
        }
        $this->hydrator = $hydrator;
        return $this;
    }

    public function setIdentifierName($identifier)
    {
        $this->identifierName = $identifier;
        return $this;
    }

    public function setIsCollection($flag)
    {
        $this->isCollection = (bool) $flag;
        return $this;
    }

    public function setResourceRoute($route)
    {
        $this->resourceRoute = $route;
        return $this;
    }

    public function setRoute($route)
    {
        $this->route = $route;
        return $this;
    }

    public function setRouteOptions(array $options)
    {
        $this->routeOptions = $options;
        return $this;
    }

    public function setRouteParams(array $params)
    {
        $this->routeParams = $params;
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }
}
