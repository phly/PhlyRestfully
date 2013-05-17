<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

class MetadataMap
{
    /**
     * @var Metadata[]
     */
    protected $map = array();

    /**
     * Constructor
     *
     * If provided, will pass $map to setMap().
     *
     * @param  null|array $map
     */
    public function __construct(array $map = null)
    {
        if (!empty($map)) {
            $this->setMap($map);
        }
    }

    /**
     * Set the metadata map
     *
     * Accepts an array of class => metadata definitions.
     * Each definition may be an instance of Metadata, or an array
     * of options used to define a Metadata instance.
     *
     * @param  array $map
     * @return self
     */
    public function setMap(array $map)
    {
        foreach ($map as $class => $options) {
            $metadata = $options;
            if (is_array($metadata)) {
                $metadata = new Metadata($class, $options);
            }

            if (!$metadata instanceof Metadata) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s expects each map to be an array or a PhlyRestfully\Metadata instance; received "%s"',
                    __METHOD__,
                    (is_object($metadata) ? get_class($metadata) : gettype($metadata))
                ));
            }

            $this->map[$class] = $metadata;
        }

        return $this;
    }

    /**
     * Does the map contain metadata for the given class?
     *
     * @param  object|string $class Object or class name to test
     * @return bool
     */
    public function has($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return array_key_exists($class, $this->map);
    }

    /**
     * Retrieve the metadata for a given class
     *
     * @param  object|string $class Object or classname for which to retrieve metadata
     * @return Metadata
     */
    public function get($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return $this->map[$class];
    }
}
