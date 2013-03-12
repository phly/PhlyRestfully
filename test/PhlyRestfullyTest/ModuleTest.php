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

    public function setupServiceManager()
    {
        $options = array('service_manager' => array(
            'invokables' => array(
                // Some hydrator services, so we have something to work with
                'Hydrator\ArraySerializable' => 'Zend\Stdlib\Hydrator\ArraySerializable',
                'Hydrator\ClassMethods'      => 'Zend\Stdlib\Hydrator\ClassMethods',
                'Hydrator\ObjectProperty'    => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'Hydrator\Reflection'        => 'Zend\Stdlib\Hydrator\Reflection',
            ),
            'factories' => array(
                // Consumed by PhlyRestfully\JsonRenderer service
                'ViewHelperManager' => 'Zend\Mvc\Service\ViewHelperManagerFactory',
            ),
        ));
        $config   = ArrayUtils::merge($options['service_manager'], $this->module->getServiceConfig());
        $services = new ServiceManager();
        $config   = new Config($config);
        $config->configureServiceManager($services);

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
        return $services;
    }

    public function testJsonRendererFactoryInjectsDefaultHydratorIfPresentInConfig()
    {
        $options = array(
            'phlyrestfully' => array(
                'renderer' => array(
                    'default_hydrator' => 'Hydrator\ObjectProperty',
                )
            ),
        );
        $services = $this->setupServiceManager();
        $services->setService('Config', $options);

        $renderer = $services->get('PhlyRestfully\JsonRenderer');
        $this->assertAttributeInstanceOf('Zend\Stdlib\Hydrator\ObjectProperty', 'defaultHydrator', $renderer);
    }

    public function testJsonRendererFactoryInjectsHydratorMappingsIfPresentInConfig()
    {
        $this->markTestIncomplete();
        $options = array(
            'phlyrestfully' => array(
                'renderer' => array(
                    'default_hydrator' => 'Hydrator\ObjectProperty',
                )
            ),
        );
        $services->setService('Config', $options);

        $renderer = $services->get('PhlyRestfully\JsonRenderer');
        $this->assertAttributeInstanceOf('Zend\Stdlib\Hydrator\ObjectProperty', 'defaultHydrator', $renderer);
    }
}
