<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use JsonSerializable;
use Traversable;
use Zend\Json\Json;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\Model\JsonModel;

/**
 * Simple extension to facilitate the specialized JsonStrategy in this Module.
 */
class RestfulJsonModel extends JsonModel
{
    /**
     * Default hydrator to use if none provided for item class
     *
     * @var HydratorInterface
     */
    protected $defaultHydrator;

    /**
     * Map of class/hydrator pairs
     *
     * @var HydratorInterface[]
     */
    protected $hydrators = array();

    /**
     * Set the default hydrator to use as a fallback
     *
     * @param  HydratorInterface $hydrator
     */
    public function setDefaultHydrator(HydratorInterface $hydrator)
    {
        $this->defaultHydrator = $hydrator;
    }

    /**
     * Retrieve default hydrator, if any
     *
     * @return null|HydratorInterface
     */
    public function getDefaultHydrator()
    {
        return $this->defaultHydrator;
    }

    /**
     * Add a class (or interface) => hydrator mapping
     *
     * @param  string $class
     * @param  HydratorInterface $hydrator
     */
    public function addHydrator($class, HydratorInterface $hydrator)
    {
        $this->hydrators[$class] = $hydrator;
    }

    /**
     * Does a hydrator exist for the given class?
     *
     * @param  object $item
     * @return bool
     */
    public function hasHydrator($item)
    {
        foreach (array_reverse($this->hydrators) as $test => $hydrator) {
            if ($item instanceof $test) {
                $class = get_class($item);
                if ($class !== $test) {
                    $this->addHydrator($class, $hydrator);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve a hydrator mapped to a given item
     *
     * @param  object $item
     * @return HydratorInterface
     * @throws Exception\RuntimeException if not found
     */
    public function getHydrator($item)
    {
        $class = get_class($item);
        if (!$this->hasHydrator($item)) {
            throw new Exception\RuntimeException(sprintf(
                'Cannot retrieve hydrator for "%s"; no match found',
                $class
            ));
        }
        return $this->hydrators[$class];
    }

    /**
     * Is this a problem api payload?
     *
     * @return bool
     */
    public function isProblemApi()
    {
        $variables = $this->getVariables();
        if ($variables instanceof Traversable) {
            $variables = iterator_to_array($variables);
        }

        $keys      = array_keys($variables);
        $expected  = array('describedBy', 'title');
        $test      = array_intersect($expected, $keys);
        if ($test == $expected) {
            return true;
        }
        return false;
    }

    /**
     * Does this describe a HAL response?
     *
     * @return bool
     */
    public function isHal()
    {
        $variables = $this->getVariables();
        if ($variables instanceof Traversable) {
            $variables = iterator_to_array($variables);
        }

        $keys = array_keys($variables);
        if (in_array('_links', $keys)) {
            return true;
        }
        return false;
    }

    /**
     * Serialize to JSON
     *
     * If the variables contain an object "item" key, the value is passed to
     * serializeItem().
     *
     * If the variables contain an iterable "items" key, the value is passed to
     * serializeItems().
     *
     * Once all values have been filtered to serializeable form, the resulting
     * variables are serialized as JSON.
     *
     * @return string
     */
    public function serialize()
    {
        if ($this->isProblemApi()) {
            return $this->serializeProblemApi();
        }

        $variables = $this->getVariables();
        if ($variables instanceof Traversable) {
            $variables = iterator_to_array($variables);
        }

        if (isset($variables['item']) && is_object($variables['item'])) {
            $variables['item'] = $this->serializeItem($variables['item']);
        }

        if (isset($variables['items']) && is_array($variables['items'])) {
            $variables['items'] = $this->serializeItems($variables['items']);
        }

        return Json::encode($variables);
    }

    /**
     * Serialize an API-Problem payload
     *
     * @return string
     */
    public function serializeProblemApi()
    {
        $variables = $this->getVariables();
        if ($variables instanceof Traversable) {
            $variables = iterator_to_array($variables);
        }


        $values = array(
            'describedBy' => '',
            'title'       => '',
            'httpStatus'  => 500,
            'detail'      => '',
        );
        foreach (array_keys($values) as $key) {
            if (isset($variables[$key])) {
                $values[$key] = $variables[$key];
                continue;
            }
            unset($values[$key]);
        }
        return Json::encode($values);
    }

    /**
     * Serialize a single item
     *
     * If the item implements JsonSerializable, it is returned as-is.
     *
     * If a hydrator has been mapped for the class of which $item is an
     * instance, that hydrator will be used to create and return an array
     * representation of the item.
     *
     * If a default hydrator exists, that hydrator will be used.
     *
     * Finally, if neither a class-specific or default hydrator is registered,
     * the item will simply be cast to an array.
     *
     * @param  object $item
     * @return array
     */
    public function serializeItem($item)
    {
        if (interface_exists('JsonSerializable') && $item instanceof JsonSerializable) {
            return $item;
        }

        $hydrator = $this->getDefaultHydrator();
        if (!$this->hasHydrator($item) && !$hydrator) {
            return (array) $item;
        }

        if ($this->hasHydrator($item)) {
            $hydrator = $this->getHydrator($item);
        }

        return $hydrator->extract($item);
    }

    /**
     * Serialize a list of items
     *
     * Each item in $items is passed to serializeItem(), and appended
     * to a new array.
     *
     * @param  array $items
     * @return array[]
     */
    public function serializeItems(array $items)
    {
        foreach ($items as $index => $info) {
            if (!is_array($info)) {
                continue;
            }
            if (!isset($info['item'])) {
                continue;
            }
            if (!is_object($info['item'])) {
                continue;
            }
            $items[$index]['item'] = $this->serializeItem($info['item']);
        }
        return $items;
    }
}
