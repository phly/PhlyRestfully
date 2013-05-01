<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Factory;

use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class ResourceControllerFactory
 * @package PhlyRestfully\Factory
 */
class ResourceControllerFactory implements AbstractFactoryInterface
{
    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $controllers
     * @param string                  $name
     * @param string                  $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $controllers, $name, $requestedName)
    {
        $services = $controllers->getServiceLocator();

        if (!$services->has('Config') || !$services->has('EventManager')) {
            // Config and EventManager are required
            return false;
        }

        $config = $services->get('Config');
        if (!isset($config['phlyrestfully'])
            || !isset($config['phlyrestfully']['resources'])
        ) {
            return false;
        }
        $config = $config['phlyrestfully']['resources'];

        if (!isset($config[$requestedName])
            || !isset($config[$requestedName]['listener'])
            || !isset($config[$requestedName]['route_name'])
        ) {
            // Configuration, and specifically the listener and route_name
            // keys, is required
            return false;
        }

        if (!$services->has($config[$requestedName]['listener'])) {
            // Service referenced by listener key is required
            throw new ServiceNotFoundException(sprintf(
                '%s requires that a valid "listener" service be specified for controller %s; no service found',
                __METHOD__,
                $requestedName
            ));
        }

        return true;
    }

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $controllers
     * @param string                  $name
     * @param string                  $requestedName
     * @return ResourceController
     * @throws ServiceNotCreatedException if listener specified is not a ListenerAggregate
     */
    public function createServiceWithName(ServiceLocatorInterface $controllers, $name, $requestedName)
    {
        $services = $controllers->getServiceLocator();
        $config   = $services->get('Config');
        $config   = $config['phlyrestfully']['resources'][$requestedName];

        $listener = $services->get($config['listener']);
        if (!$listener instanceof ListenerAggregateInterface) {
            throw new ServiceNotCreatedException(sprintf(
                '%s expects that the "listener" reference a service that implements Zend\EventManager\ListenerAggregateInterface; received %s',
                __METHOD__,
                (is_object($listener) ? get_class($listener) : gettype($listener))
            ));
        }

        $events = $services->get('EventManager');
        $events->attach($listener);

        $resource = new Resource();
        $resource->setEventManager($events);

        $controller = new ResourceController();
        $controller->setResource($resource);
        $this->setControllerOptions($config, $controller);

        return $controller;
    }

    /**
     * Loop through configuration to discover and set controller options.
     * 
     * @param  array $config 
     * @param  ResourceController $controller 
     */
    protected function setControllerOptions(array $config, ResourceController $controller)
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'accept_criteria':
                    $controller->setAcceptCriteria($value);
                    break;

                case 'collection_http_options':
                    $controller->setCollectionHttpOptions($value);
                    break;

                case 'collection_name':
                    $controller->setCollectionName($value);
                    break;

                case 'content_types':
                    $controller->setContentTypes($value);
                    break;

                case 'identifier_name':
                    $controller->setIdentifierName($value);
                    break;

                case 'page_size':
                    $controller->setPageSize($value);
                    break;

                case 'resource_http_options':
                    $controller->setResourceHttpOptions($value);
                    break;

                case 'route_name':
                    $controller->setRoute($value);
                    break;
            }
        }
    }
}
