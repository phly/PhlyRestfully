.. _phlyrestfully.basics.example:

Example
=======

The following is an example detailing a service that allows creating new
resources and fetching existing resources only. It could be expanded to allow
updating, patching, and deletion, but the basic premise stays the same.

First, I'll define an interface for persistence. I'm doing this in order to
focus on the pieces related to the API; how you actually persist your data is
completely up to you.

.. code-block:: php
    :linenos:

    namespace Paste;

    interface PersistenceInterface
    {
        public function save(array $data);
        public function fetch($id);
        public function fetchAll();
    }

Next, I'll create a resource listener. This example assumes you are using Zend
Framework 2.2.0 or above, which includes the ``AbstractListenerAggregate``; if
you are using a previous version, you will need to manually implement the
``ListenerAggregateInterface`` and its ``detach()`` method.

.. code-block:: php
    :linenos:

    namespace Paste;

    use PhlyRestfully\Exception\CreationException;
    use PhlyRestfully\Exception\DomainException;
    use PhlyRestfully\ResourceEvent;
    use Zend\EventManager\AbstractListenerAggregate;
    use Zend\EventManager\EventManagerInterface;

    class PasteResourceListener extends AbstractListenerAggregate
    {
        protected $persistence;

        public function __construct(PersistenceInterface $persistence)
        {
            $this->persistence = $persistence;
        }

        public function attach(EventManagerInterface $events)
        {
            $this->listeners[] = $events->attach('create', array($this, 'onCreate'));
            $this->listeners[] = $events->attach('fetch', array($this, 'onFetch'));
            $this->listeners[] = $events->attach('fetchAll', array($this, 'onFetchAll'));
        }

        public function onCreate(ResourceEvent $e)
        {
            $data  = $e->getParam('data');
            $paste = $this->persistence->save($data);
            if (!$paste) {
                throw new CreationException();
            }
            return $paste;
        }

        public function onFetch(ResourceEvent $e)
        {
            $id = $e->getParam('id');
            $paste = $this->persistence->fetch($id);
            if (!$paste) {
                throw new DomainException('Paste not found', 404);
            }
            return $paste;
        }

        public function onFetchAll(ResourceEvent $e)
        {
            return $this->persistence->fetchAll();
        }
    }

The job of the listeners is to pull arguments from the passed event instance,
and then work with the persistence storage. Based on what is returned we either
throw an exception with appropriate messages and/or codes, or we return a
result.

Now that we have a resource listener, we can begin integrating it into our
application.

For the purposes of our example, we'll assume:

- The persistence engine is returning arrays (or arrays of arrays, when it comes
  to ``fetchAll()``.
- The identifier field in each array is simply "id".

First, let's create a route. In our module's configuration file, usually
``config/module.config.php``, we'd add the following routing definitions:

.. code-block:: php
    :linenos:

    'router' => array('routes' => array(
        'paste' => array(
            'type' => 'Literal',
            'options' => array(
                'route' => '/paste',
                'controller' => 'Paste\PasteController', // for the web UI
            ),
            'may_terminate' => true,
            'child_routes' => array(
                'api' => array(
                    'type' => 'Segment',
                    'options' => array(
                        'route'      => '/api/pastes[/:id]',
                        'controller' => 'Paste\ApiController',
                    ),
                ),
            ),
        ),
    )),

I defined a top-level route for the namespace, which will likely be accessible
via a web UI, and will have a different controller. For the purposes of this
example, we'll ignore that for now. The import route is ``paste/api``, which is
our RESTful endpoint.

Next, let's define the controller configuration. Again, inside our module
configuration, we'll add configuration, this time under the ``phlyrestfully``
key and its ``resources`` subkey.

.. code-block:: php
    :linenos:

    'phlyrestfully' => array(
        'resources' => array(
            'Paste\ApiController' => array(
                'identifier'              => 'Pastes',
                'listener'                => 'Paste\PasteResourceListener',
                'resource_identifiers'    => array('PasteResource'),
                'collection_http_options' => array('get', 'post'),
                'collection_name'         => 'pastes',
                'page_size'               => 10,
                'resource_http_options'   => array('get'),
                'route_name'              => 'paste/api',
            ),
        ),
    ),

Notie that the configuration is a subset of all configuration at this point;
we're only defining the options needed for our particular resource.

Now, how can we get our ``PasteResourceListener`` instance? Remember, it
requires a ``PersistenceInterface`` instance to the constructor. Let's add a
factory inside our ``Module`` class. The full module class is presented here.

.. code-block:: php
    :linenos:

    namespace Paste;

    class Module
    {
        public function getConfig()
        {
            return include __DIR__ . '/config/module.config.php';
        }

        public function getAutoloaderConfig()
        {
            return array(
                'Zend\Loader\StandardAutoloader' => array(
                    'namespaces' => array(
                        __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                    ),
                ),
            );
        }

        public function getServiceConfig()
        {
            return array('factories' => array(
                'Paste\PasteResourceListener' => function ($services) {
                    $persistence = $services->get('Paste\PersistenceInterface');
                    return new PasteResourceListener($persistence);
                },
            ));
        }
    }

.. note::

    I lied: I'm not giving the full configuration. The reason is that I'm not
    defining the actual persistence implementation in the example. If you
    continue with the example, you would need to define it, and assign a factory
    to the service name ``Paste\PersistenceInterface``.

At this point, we're done! Register your module with the application
configuration (usually ``config/application.config.php``), and you should
immediately be able to access the API.

.. note::

    When hitting the API, make sure you send an Accept header with either the
    content type ``application/json``, ``application/hal+json``, or
    ``text/json``; otherwise, it will try to deliver HTML to you, and, unless
    you have defined view scripts accordingly, you will see errors.
