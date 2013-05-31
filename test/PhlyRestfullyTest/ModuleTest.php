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
use ReflectionObject;
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
                'ViewHelperManager'       => 'Zend\Mvc\Service\ViewHelperManagerFactory',
                'ControllerPluginManager' => 'Zend\Mvc\Service\ControllerPluginManagerFactory',
            ),
        ));
        $config = ArrayUtils::merge($options['service_manager'], $this->module->getServiceConfig());
        $config['view_helpers']       = $this->module->getViewHelperConfig();
        $config['controller_plugins'] = $this->module->getControllerPluginConfig();

        $services       = new ServiceManager();
        $servicesConfig = new Config($config);
        $servicesConfig->configureServiceManager($services);
        $services->setService('Config', $config);

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

        $helpers = $services->get('ViewHelperManager');
        $helpersConfig = new Config($config['view_helpers']);
        $helpersConfig->configureServiceManager($helpers);

        $plugins = $services->get('ControllerPluginManager');
        $pluginsConfig = new Config($config['controller_plugins']);
        $pluginsConfig->configureServiceManager($plugins);

        return $services;
    }

    public function testJsonRendererFactoryInjectsDefaultHydratorIfPresentInConfig()
    {
        $options = array(
            'phlyrestfully' => array(
                'renderer' => array(
                    'default_hydrator' => 'Hydrator\ObjectProperty',
                ),
            ),
        );

        $services = $this->setupServiceManager();
        $config   = $services->get('Config');
        $services->setAllowOverride(true);
        $services->setService('Config', ArrayUtils::merge($config, $options));

        $helpers = $services->get('ViewHelperManager');
        $plugin  = $helpers->get('HalLinks');
        $this->assertAttributeInstanceOf('Zend\Stdlib\Hydrator\ObjectProperty', 'defaultHydrator', $plugin);
    }

    public function testJsonRendererFactoryInjectsHydratorMappingsIfPresentInConfig()
    {
        $options = array(
            'phlyrestfully' => array(
                'renderer' => array(
                    'hydrators' => array(
                        'Some\MadeUp\Component'            => 'Hydrator\ClassMethods',
                        'Another\MadeUp\Component'         => 'Hydrator\Reflection',
                        'StillAnother\MadeUp\Component'    => 'Hydrator\ArraySerializable',
                        'A\Component\With\SharedHydrators' => 'Hydrator\Reflection',
                    ),
                ),
            ),
        );

        $services = $this->setupServiceManager();
        $config   = $services->get('Config');
        $services->setAllowOverride(true);
        $services->setService('Config', ArrayUtils::merge($config, $options));

        $helpers = $services->get('ViewHelperManager');
        $plugin  = $helpers->get('HalLinks');

        $r             = new ReflectionObject($plugin);
        $hydratorsProp = $r->getProperty('hydratorMap');
        $hydratorsProp->setAccessible(true);
        $hydrators = $hydratorsProp->getValue($plugin);

        $this->assertInternalType('array', $hydrators);

        foreach ($options['phlyrestfully']['renderer']['hydrators'] as $class => $serviceName) {
            $key = strtolower($class);
            $this->assertArrayHasKey($key, $hydrators);
            $hydrator = $hydrators[$key];
            $this->assertSame($services->get($serviceName), $hydrator);
        }
    }
}
