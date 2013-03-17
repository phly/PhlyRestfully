<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Object describing a collection of link relations
 */
class LinkCollection implements Countable, IteratorAggregate
{
    /**
     * @var array
     */
    protected $links = array();

    /**
     * Return a count of link relations
     * 
     * @return int
     */
    public function count()
    {
        return count($this->links);
    }

    /**
     * Retrieve internal iterator
     * 
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->links);
    }

    /**
     * Add a link
     * 
     * @param  Link $link 
     * @param  bool $overwrite 
     * @return self
     */
    public function add(Link $link, $overwrite = false)
    {
        $relation = $link->getRelation();
        if (!isset($this->links[$relation]) || $overwrite) {
            $this->links[$relation] = $link;
            return $this;
        }

        if ($this->links[$relation] instanceof Link) {
            $this->links[$relation] = array($this->links[$relation]);
        }

        if (!is_array($this->links[$relation])) {
            throw new Exception\DomainException(sprintf(
                '%s::$links should be either a %s\Link or an array; however, it is a "%s"',
                __CLASS__,
                __NAMESPACE__,
                (is_object($this->links[$relation]) ? get_class($this->links[$relation]) : gettype($this->links[$relation]))
            ));
        }

        $this->links[$relation][] = $link;
        return $this;
    }

    /**
     * Retrieve a link relation
     * 
     * @param  string $relation 
     * @return Link|array
     */
    public function get($relation)
    {
        if (!$this->has($relation)) {
            return null;
        }
        return $this->links[$relation];
    }

    /**
     * Does a given link relation exist?
     * 
     * @param  string $relation 
     * @return bool
     */
    public function has($relation)
    {
        return array_key_exists($relation, $this->links);
    }

    /**
     * Remove a given link relation
     * 
     * @param  string $relation 
     * @return bool
     */
    public function remove($relation)
    {
        if (!$this->has($relation)) {
            return false;
        }
        unset($this->links[$relation]);
        return true;
    }
}
