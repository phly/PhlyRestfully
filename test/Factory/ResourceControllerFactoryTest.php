<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Factory;

use PhlyRestfully\ResourceController;
use PhlyRestfully\Factory\ResourceControllerFactory;
use PHPUnit\Framework\TestCase as TestCase;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use Laminas\Mvc\Controller\ControllerManager;
use Laminas\Mvc\Service;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\ServiceManager\Config;

class ResourceControllerFactoryTest extends TestCase
{
    public function setUp(): void
    {
        $this->services    = $services    = new ServiceManager();
        $this->controllers = $controllers = new ControllerManager($this->services);
        $this->factory     = $factory     = new ResourceControllerFactory();

        $controllers->addAbstractFactory($factory);

        $services->setService(ServiceLocatorInterface::class, $services);
        $services->setService('config', $this->getConfig());
        $services->setService('ControllerManager', $controllers);
        $services->setFactory('ControllerPluginManager', Service\ControllerPluginManagerFactory::class);
        $services->setInvokableClass('EventManager', EventManager::class);
        $services->setInvokableClass('SharedEventManager', SharedEventManager::class);
        $services->setShared('EventManager', false);
    }

    public function getConfig()
    {
        return [
            'phlyrestfully' => [
                'resources' => [
                    'ApiController' => [
                        'listener'   => TestAsset\Listener::class,
                        'route_name' => 'api',
                    ],
                ],
            ],
        ];
    }

    /**
     * @group fail
     */
    public function testWillInstantiateListenerIfServiceNotFoundButClassExists(): void
    {
        $this->assertTrue($this->controllers->has('ApiController'));
        $controller = $this->controllers->get('ApiController');
        $this->assertInstanceOf(ResourceController::class, $controller);
    }

    public function testWillInstantiateAlternateResourceControllerWhenSpecified(): void
    {
        $config = $this->services->get('config');
        $config['phlyrestfully']['resources']['ApiController']['controller_class'] = TestAsset\CustomController::class;
        $this->services->setAllowOverride(true);
        $this->services->setService('config', $config);
        $controller = $this->controllers->get('ApiController');
        $this->assertInstanceOf(TestAsset\CustomController::class, $controller);
    }
}
