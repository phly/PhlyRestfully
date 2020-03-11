<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Module;
use PHPUnit\Framework\TestCase as TestCase;
use ReflectionObject;
use Laminas\Hydrator;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\RouteMatch;
use Laminas\Mvc\Router\RouteStackInterface;
use Laminas\Mvc\Service;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;

class ModuleTest extends TestCase
{
    public function setUp(): void
    {
        $this->module = new Module;
    }

    public function setupServiceManager()
    {
        $options = ['service_manager' => [
            'factories' => [
                // Consumed by PhlyRestfully\JsonRenderer service
                'ViewHelperManager'       => Service\ViewHelperManagerFactory::class,
                'ControllerPluginManager' => Service\ControllerPluginManagerFactory::class,
            ],
        ]];
        $config = ArrayUtils::merge($options['service_manager'], $this->module->getServiceConfig());
        $config['view_helpers']       = $this->module->getViewHelperConfig();
        $config['controller_plugins'] = $this->module->getControllerPluginConfig();

        $services       = new ServiceManager();
        $servicesConfig = new Config($config);
        $servicesConfig->configureServiceManager($services);
        $services->setService('config', $config);

        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch([]));

        $router = $this->getMockBuilder(RouteStackInterface::class)->getMock();
        $services->setService('HttpRouter', $router);

        $app = $this->getMockBuilder(Application::class)
                    ->disableOriginalConstructor()
                    ->getMock();
        $app->expects($this->once())
            ->method('getMvcEvent')
            ->will($this->returnValue($event));
        $services->setService('Application', $app);

        $helpers = $services->get('ViewHelperManager');
        $helpersConfig = new Config($config['view_helpers']);
        $helpersConfig->configureServiceManager($helpers);

        $plugins = $services->get('ControllerPluginManager');
        $pluginsConfig = new Config($config['controller_plugins']);
        $pluginsConfig->configureServiceManager($plugins);

        return $services;
    }

    public function testJsonRendererFactoryInjectsDefaultHydratorIfPresentInConfig(): void
    {
        $options = [
            'phlyrestfully' => [
                'renderer' => [
                    'default_hydrator' => 'ObjectProperty',
                ],
            ],
        ];

        $services = $this->setupServiceManager();
        $config   = $services->get('config');
        $services->setAllowOverride(true);
        $services->setService('config', ArrayUtils::merge($config, $options));

        $helpers = $services->get('ViewHelperManager');
        $plugin  = $helpers->get('HalLinks');
        $this->assertAttributeInstanceOf(Hydrator\ObjectProperty::class, 'defaultHydrator', $plugin);
    }

    public function testJsonRendererFactoryInjectsHydratorMappingsIfPresentInConfig(): void
    {
        $options = [
            'phlyrestfully' => [
                'renderer' => [
                    'hydrators' => [
                        'Some\MadeUp\Component'            => 'ClassMethods',
                        'Another\MadeUp\Component'         => 'Reflection',
                        'StillAnother\MadeUp\Component'    => 'ArraySerializable',
                        'A\Component\With\SharedHydrators' => 'Reflection',
                    ],
                ],
            ],
        ];

        $services = $this->setupServiceManager();
        $config   = $services->get('config');
        $services->setAllowOverride(true);
        $services->setService('config', ArrayUtils::merge($config, $options));

        $helpers = $services->get('ViewHelperManager');
        $plugin  = $helpers->get('HalLinks');

        $r             = new ReflectionObject($plugin);
        $hydratorsProp = $r->getProperty('hydratorMap');
        $hydratorsProp->setAccessible(true);
        $hydratorMap = $hydratorsProp->getValue($plugin);

        $hydrators   = $plugin->getHydratorManager();

        $this->assertIsArray($hydratorMap);

        foreach ($options['phlyrestfully']['renderer']['hydrators'] as $class => $serviceName) {
            $key = strtolower($class);
            $this->assertArrayHasKey($key, $hydratorMap);
            $hydrator = $hydratorMap[$key];
            $this->assertSame(get_class($hydrators->get($serviceName)), get_class($hydrator));
        }
    }
}
