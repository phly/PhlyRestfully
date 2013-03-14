<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Traversable;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;

/**
 * Base resource class
 *
 * Essentially, simply marshalls arguments and triggers events; it is useless
 * without listeners to do the actual work.
 */
class Resource implements ResourceInterface
{
    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * Set event manager instance
     *
     * Sets the event manager identifiers to the current class, this class, and
     * the resource interface.
     *
     * @param  EventManagerInterface $events
     * @return Resource
     */
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

    /**
     * Retrieve event manager
     *
     * Lazy-instantiates an EM instance if none provided.
     *
     * @return EventManagerInterface
     */
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
     * Expects either an array or object representing the item to create. If
     * a non-array, non-object is provided, raises an exception.
     *
     * The value returned by the last listener to the "create" event will be
     * returned as long as it is an array or object; otherwise, the original
     * $data is returned. If you wish to indicate failure to create, raise a
     * PhlyRestfully\Exception\CreationException from a listener.
     *
     * @param  array|object $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    public function create($data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }
        if (!is_object($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Data provided to create must be either an array or object; received "%s"',
                gettype($data)
            ));
        }

        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('data' => $data));
        $last    = $results->last();
        if (!is_array($last) && !is_object($last)) {
            return $data;
        }
        return $last;
    }

    /**
     * Update (replace) an existing item
     *
     * Updates the item indicated by $id, replacing it with the information
     * in $data. $data should be a full representation of the item, and should
     * be an array or object; if otherwise, an exception will be raised.
     *
     * Like create(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned. If you wish to indicate failure to update, raise a
     * PhlyRestfully\Exception\UpdateException.
     *
     * @param  string|int $id
     * @param  array|object $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    public function update($id, $data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }
        if (!is_object($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Data provided to update must be either an array or object; received "%s"',
                gettype($data)
            ));
        }

        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('id' => $id, 'data' => $data));
        $last    = $results->last();
        if (!is_array($last) && !is_object($last)) {
            return $data;
        }
        return $last;
    }

    /**
     * Update (replace) an existing collection of items
     *
     * Replaces the collection with  the items contained in $data.
     * $data should be a multidimensional array or array of objects; if
     * otherwise, an exception will be raised.
     *
     * Like update(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned. If you wish to indicate failure to update, raise a
     * PhlyRestfully\Exception\UpdateException.
     *
     * @param  array $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    public function replaceList($data)
    {
        if (!is_array($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Data provided to replaceList must be either a multidimensional array or array of objects; received "%s"',
                gettype($data)
            ));
        }
        array_walk($data, function($value, $key) use(&$data) {
            if (is_array($value)) {
                $data[$key] = (object) $value;
                return;
            }

            if (!is_object($value)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Data provided to replaceList must contain only arrays or objects; received "%s"',
                    gettype($value)
                ));
            }
        });
        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('data' => $data));
        $last    = $results->last();
        if (!is_array($last) && !is_object($last)) {
            return $data;
        }
        return $last;
    }

    /**
     * Partial update of an existing item
     *
     * Update the item indicated by $id, using the information from $data;
     * $data should be merged with the existing item in order to provide a
     * partial update. Additionally, $data should be an array or object; any
     * other value will raise an exception.
     *
     * Like create(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned. If you wish to indicate failure to update, raise a
     * PhlyRestfully\Exception\PatchException.
     *
     * @param  string|int $id
     * @param  array|object $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    public function patch($id, $data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }
        if (!is_object($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Data provided to create must be either an array or object; received "%s"',
                gettype($data)
            ));
        }

        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('id' => $id, 'data' => $data));
        $last    = $results->last();
        if (!is_array($last) && !is_object($last)) {
            return $data;
        }
        return $last;
    }

    /**
     * Delete an existing item
     *
     * Use to delete the item indicated by $id. The value returned by the last
     * listener will be used, as long as it is a boolean; otherwise, a boolean
     * false will be returned, indicating failure to delete.
     *
     * @param  string|int $id
     * @return bool
     */
    public function delete($id)
    {
        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('id' => $id));
        $last    = $results->last();
        if (!is_bool($last)) {
            return false;
        }
        return $last;
    }

    /**
     * Delete an existing collection of records
     *
     * @param  null|array $data
     * @return bool
     */
    public function deleteList($data = null)
    {
        if ($data
            && (!is_array($data) && !$data instanceof Traversable)
        ) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a null argument, or an array/Traversable of items and/or ids; received %s',
                __METHOD__,
                gettype($data)
            ));
        }
        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('data' => $data));
        $last    = $results->last();
        if (!is_bool($last)) {
            return false;
        }
        return $last;
    }

    /**
     * Fetch an existing item
     *
     * Retrieve an existing item indicated by $id. The value of the last
     * listener will be returned, as long as it is an array or object;
     * otherwise, a boolean false value will be returned, indicating a
     * lookup failure.
     *
     * @param  string|int $id
     * @return false|array|object
     */
    public function fetch($id)
    {
        $events  = $this->getEventManager();
        $results = $events->trigger(__FUNCTION__, $this, array('id' => $id));
        $last    = $results->last();
        if (!is_array($last) && !is_object($last)) {
            return false;
        }
        return $last;
    }

    /**
     * Fetch a collection of items
     *
     * Use to retrieve a collection of items. The value of the last
     * listener will be returned, as long as it is an array or Traversable;
     * otherwise, an empty array will be returned.
     *
     * The recommendation is to return a \Zend\Paginator\Paginator instance,
     * which will allow performing paginated sets, and thus allow the view
     * layer to select the current page based on the query string or route.
     *
     * @return array|Traversable
     */
    public function fetchAll()
    {
        $events  = $this->getEventManager();
        $params  = func_get_args();
        if (!empty($params)) {
            $params = $events->prepareArgs($params);
        }
        $results = $events->trigger(__FUNCTION__, $this, $params);
        $last    = $results->last();
        if (!is_array($last) 
            && !$last instanceof HalCollection
            && !$last instanceof Traversable
        ) {
            return array();
        }
        return $last;
    }
}
