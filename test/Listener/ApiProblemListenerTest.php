<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Listener;

use PhlyRestfully\Listener\ApiProblemListener;
use PHPUnit\Framework\TestCase as TestCase;
use Laminas\Console\Request as ConsoleRequest;
use Laminas\Console\Response as ConsoleResponse;
use Laminas\Mvc\MvcEvent;

class ApiProblemListenerTest extends TestCase
{
    public function setUp(): void
    {
        $this->event    = new MvcEvent();
        $this->event->setError('this is an error event');
        $this->listener = new ApiProblemListener();
    }

    public function testOnRenderReturnsEarlyWhenConsoleRequestDetected(): void
    {
        $this->event->setRequest(new ConsoleRequest());

        $this->assertNull($this->listener->onRender($this->event));
    }
}
