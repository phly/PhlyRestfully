<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

class HalItem
{
    protected $id;

    protected $item;

    protected $route;

    protected $routeParams;

    /**
     * @param  object|array $item 
     * @param  mixed $id 
     * @param  string $route 
     * @param  array $routeParams 
     * @throws Exception\InvalidItemException if item is not an object or array
     */
    public function __construct($item, $id, $route, array $routeParams = array())
    {
        if (!is_object($item) && !is_array($item)) {
            throw new Exception\InvalidItemException();
        }

        $this->item        = $item;
        $this->id          = $id;
        $this->route       = (string) $route;
        $this->routeParams = $routeParams;
    }

    public function __get($name)
    {
        $names = array(
            'item'         => 'item',
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
}
