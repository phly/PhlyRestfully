<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Module;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

class ModuleTest extends TestCase
{
    public function setUp()
    {
        $this->module = new Module;
    }

    public function testJsonRendererFactoryInjectsHydratorIfPresentInConfig()
    {
        $options = array(
            'phlyrestfully' => array(
                'renderer' => array(
                    'default_hydrator' => 'Hydrator\ObjectProperty',
                )
            ),
            'service_manager' => array(
                'invokables' => array(
                    // Created so we have a service for the default_hydrator
                    'Hydrator\ObjectProperty' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                ),
                'factories' => array(
                    // Consumed by PhlyRestfully\JsonRenderer service
                    'ViewHelperManager' => 'Zend\Mvc\Service\ViewHelperManagerFactory',
                ),
            ),
        );
        $config   = ArrayUtils::merge($options['service_manager'], $this->module->getServiceConfig());
        $services = new ServiceManager();
        $config   = new Config($config);
        $config->configureServiceManager($services);
        $services->setService('Config', $options);

        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(array()));

        $router = $this->getMock('Zend\Mvc\Router\RouteStackInterface');
        $services->setService('HttpRouter', $router);

        $app = $this->getMockBuilder('Zend\Mvc\Application')
                    ->disableOriginalConstructor()
                    ->getMock();
        $app->expects($this->once())
            ->method('getMvcEvent')
            ->will($this->returnValue($event));
        $services->setService('application', $app);

        $renderer = $services->get('PhlyRestfully\JsonRenderer');
        $this->assertAttributeInstanceOf('Zend\Stdlib\Hydrator\ObjectProperty', 'defaultHydrator', $renderer);
    }
}
