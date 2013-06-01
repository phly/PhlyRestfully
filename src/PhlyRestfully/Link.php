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
use Zend\Uri\Exception as UriException;
use Zend\Uri\UriFactory;

/**
 * Object describing a link relation
 */
class Link
{
    /**
     * @var string
     */
    protected $relation;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeOptions = array();

    /**
     * @var array
     */
    protected $routeParams = array();

    /**
     * @var string
     */
    protected $url;

    /**
     * Create a link relation
     *
     * @todo  filtering and/or validation of relation string
     * @param string $relation
     */
    public function __construct($relation)
    {
        $this->relation = (string) $relation;
    }

    /**
     * Set the route to use when generating the relation URI
     *
     * If any params or options are passed, those will be passed to route assembly.
     *
     * @param  string $route
     * @param  null|array|Traversable $params
     * @param  null|array|Traversable $options
     * @return self
     */
    public function setRoute($route, $params = null, $options = null)
    {
        if ($this->hasUrl()) {
            throw new Exception\DomainException(sprintf(
                '%s already has a URL set; cannot set route',
                __CLASS__
            ));
        }

        $this->route = (string) $route;
        if ($params) {
            $this->setRouteParams($params);
        }
        if ($options) {
            $this->setRouteOptions($options);
        }
        return $this;
    }

    /**
     * Set route assembly options
     *
     * @param  array|Traversable $options
     * @return self
     */
    public function setRouteOptions($options)
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

        $this->routeOptions = $options;
        return $this;
    }

    /**
     * Set route assembly parameters/substitutions
     *
     * @param  array|Traversable $params
     * @return self
     */
    public function setRouteParams($params)
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

        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set an explicit URL for the link relation
     *
     * @param  string $url
     * @return self
     */
    public function setUrl($url)
    {
        if ($this->hasRoute()) {
            throw new Exception\DomainException(sprintf(
                '%s already has a route set; cannot set URL',
                __CLASS__
            ));
        }

        try {
            $uri = UriFactory::factory($url);
        } catch (UriException\ExceptionInterface $e) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Received invalid URL: %s',
                $e->getMessage()
            ), $e->getCode, $e);
        }

        if (!$uri->isValid()) {
            throw new Exception\InvalidArgumentException(
                'Received invalid URL'
            );
        }

        $this->url = $uri->toString();
        return $this;
    }

    /**
     * Retrieve the link relation
     *
     * @return string
     */
    public function getRelation()
    {
        return $this->relation;
    }

    /**
     * Return the route to be used to generate the link URL, if any
     *
     * @return null|string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Retrieve route assembly options, if any
     *
     * @return array
     */
    public function getRouteOptions()
    {
        return $this->routeOptions;
    }

    /**
     * Retrieve route assembly parameters/substitutions, if any
     *
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Retrieve the link URL, if set
     *
     * @return null|string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Is the link relation complete -- do we have either a URL or a route set?
     *
     * @return bool
     */
    public function isComplete()
    {
        return (!empty($this->url) || !empty($this->route));
    }

    /**
     * Does the link have a route set?
     *
     * @return bool
     */
    public function hasRoute()
    {
        return !empty($this->route);
    }

    /**
     * Does the link have a URL set?
     *
     * @return bool
     */
    public function hasUrl()
    {
        return !empty($this->url);
    }
}
