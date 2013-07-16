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

        if (!$services->has($config[$requestedName]['listener'])
            && !class_exists($config[$requestedName]['listener'])
        ) {
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

        if ($services->has($config['listener'])) {
            $listener = $services->get($config['listener']);
        } else {
            $listener = new $config['listener'];
        }

        if (!$listener instanceof ListenerAggregateInterface) {
            throw new ServiceNotCreatedException(sprintf(
                '%s expects that the "listener" reference a service that implements Zend\EventManager\ListenerAggregateInterface; received %s',
                __METHOD__,
                (is_object($listener) ? get_class($listener) : gettype($listener))
            ));
        }

        $resourceIdentifiers = array(get_class($listener));
        if (isset($config['resource_identifiers'])) {
            if (!is_array($config['resource_identifiers'])) {
                $config['resource_identifiers'] = (array) $config['resource_identifiers'];
            }
            $resourceIdentifiers = array_merge($resourceIdentifiers, $config['resource_identifiers']);
        }

        $events = $services->get('EventManager');
        $events->attach($listener);
        $events->setIdentifiers($resourceIdentifiers);

        $resource = new Resource();
        $resource->setEventManager($events);

        $identifier = $requestedName;
        if (isset($config['identifier'])) {
            $identifier = $config['identifier'];
        }

        $events     = $services->get('EventManager');
        $controller = new ResourceController($identifier);
        $controller->setEventManager($events);
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

                case 'collection_query_whitelist':
                    if (is_string($value)) {
                        $value = (array) $value;
                    }
                    if (!is_array($value)) {
                        break;
                    }

                    // Create a listener that checks the query string against
                    // the whitelisted query parameters in order to seed the
                    // collection route options.
                    $whitelist = $value;
                    $controller->getEventManager()->attach('getList.post', function ($e) use ($whitelist) {
                        $request = $e->getTarget()->getRequest();
                        if (!method_exists($request, 'getQuery')) {
                            return;
                        }
                        $query  = $request->getQuery();
                        $params = array();
                        foreach ($query as $key => $value) {
                            if (!in_array($key, $whitelist)) {
                                continue;
                            }
                            $params[$key] = $value;
                        }
                        if (empty($params)) {
                            return;
                        }

                        $collection = $e->getParam('collection');
                        $collection->setCollectionRouteOptions(array(
                            'query' => $params,
                        ));
                    });
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

                case 'page_size_param':
                    $controller->setPageSizeParam($value);
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
