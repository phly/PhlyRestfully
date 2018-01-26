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

    /**
     * @param  object|array $resource
     * @param  mixed $id
     * @throws Exception\InvalidResourceException if resource is not an object or array
     */
    public function __construct($resource, $id)
    {
        if (!is_object($resource) && !is_array($resource)) {
            throw new Exception\InvalidResourceException();
        }

        $this->resource    = $resource;
        $this->id          = $id;
    }

    /**
      * Check if properties are set
      *
      * @param  string $name
      * @throws Exception\InvalidArgumentException
      * @return mixed
      */
    public function __isset($name)
    {
        return in_array(strtolower($name), ['resource', 'id'], true);
    }

    /**
     * Retrieve properties
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $name = strtolower($name);
        if (! $this->__isset($name)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid property name "%s"',
                $name
            ));
        }
        return $this->{$name};
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
