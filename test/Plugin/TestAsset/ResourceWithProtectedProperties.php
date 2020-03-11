<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Plugin\TestAsset;

use Laminas\Stdlib\ArraySerializableInterface;

class ResourceWithProtectedProperties implements ArraySerializableInterface
{
    protected $id;
    protected $name;

    public function __construct($id, $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }

    /**
     * Exchange internal values from provided array
     *
     * @param  array $array
     */
    public function exchangeArray(array $array): void
    {
        foreach ($array as $key => $value) {
            switch ($key) {
                case 'id':
                    $this->id = $value;
                    break;
                case 'name':
                    $this->name = $value;
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }
}
