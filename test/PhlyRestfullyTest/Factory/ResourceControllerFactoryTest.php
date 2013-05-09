<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Factory;

use PhlyRestfully\Factory\ResourceControllerFactory;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Controller\ControllerManager;
use Zend\ServiceManager\ServiceManager;

class ResourceControllerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->services    = $services    = new ServiceManager();
        $this->controllers = $controllers = new ControllerManager();
        $this->factory     = $factory     = new ResourceControllerFactory();

        $controllers->addAbstractFactory($factory);
        $controllers->setServiceLocator($services);

        $services->setService('Zend\ServiceManager\ServiceLocatorInterface', $services);
        $services->setService('Config', $this->getConfig());
        $services->setService('ControllerLoader', $controllers);
        $services->setFactory('ControllerPluginManager', 'Zend\Mvc\Service\ControllerPluginManagerFactory');
        $services->setInvokableClass('EventManager', 'Zend\EventManager\EventManager');
        $services->setInvokableClass('SharedEventManager', 'Zend\EventManager\SharedEventManager');
        $services->setShared('EventManager', false);
    }

    public function getConfig()
    {
        return array(
            'phlyrestfully' => array(
                'resources' => array(
                    'ApiController' => array(
                        'listener'   => 'PhlyRestfullyTest\Factory\TestAsset\Listener',
                        'route_name' => 'api',
                    ),
                ),
            ),
        );
    }

    /**
     * @group fail
     */
    public function testWillInstantiateListenerIfServiceNotFoundButClassExists()
    {
        $this->assertTrue($this->controllers->has('ApiController'));
        $controller = $this->controllers->get('ApiController');
        $this->assertInstanceOf('PhlyRestfully\ResourceController', $controller);
    }
}
