<?php

namespace PhlyRestfully;

class MetadataMap
{
    protected $map = array();

    public function __construct(array $map = null)
    {
        if (!empty($map)) {
            $this->setMap($map);
        }
    }

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
    }

    public function has($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return array_key_exists($class, $this->map);
    }

    public function get($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return $this->map[$class];
    }
}
