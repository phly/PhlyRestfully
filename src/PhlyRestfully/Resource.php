<?php

namespace PhlyRestfully;

use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;

class Resource implements ResourceInterface
{
    protected $events;

    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            get_class($this),
            __CLASS__,
            'PhlyRestfully\ResourceInterface',
        ));
        $this->events = $events;
        return $this;
    }

    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Create a record in the resource
     * 
     * @param  array|object $data 
     * @return array|object
     */
    public function create($data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }
        if (!is_object($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Data provided to create must be either an array or object; received "%s"',
                getttype($data)
            ));
        }

        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('data' => $data));
        $last    = $results->last();
        if (!$last) {
            return $data;
        }
        return $last;
    }

    /**
     * Update (replace) an existing record
     * 
     * @param  string|int $id 
     * @param  array|object $data 
     * @return array|object
     */
    public function update($id, $data)
    {
    }

    /**
     * Partial update of an existing record
     * 
     * @param  string|int $id 
     * @param  array|object $data 
     * @return array|object
     */
    public function patch($id, $data)
    {
    }

    /**
     * Delete an existing record
     * 
     * @param  string|int $id 
     * @return bool
     */
    public function delete($id)
    {
    }

    /**
     * Fetch an existing record
     * 
     * @param  string|int $id 
     * @return false|array|object
     */
    public function fetch($id)
    {
    }

    /**
     * Fetch a collection of records
     * 
     * @return \Zend\Paginator\Paginator
     */
    public function fetchAll()
    {
    }
}
