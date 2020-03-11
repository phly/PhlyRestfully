<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Factory\TestAsset;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;

class Listener implements ListenerAggregateInterface
{
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
    }

    public function detach(EventManagerInterface $events): void
    {
    }
}
