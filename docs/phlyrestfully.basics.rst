.. _phlyrestfully.basics:

PhlyRestfully Basics
====================

PhlyRestfully allows you to create RESTful JSON APIs that adhere to
:ref:`Hypermedia Application Language <phlyrestfully.hal-primer>`. For error
handling, it uses :ref:`API-Problem <phlyrestfully.error-reporting>`.

The pieces you need to implement, work with, or understand are:

- Writing event listeners for the various ``PhlyRestfully\Resource`` events,
  which will be used to either persist resources or fetch resources from
  persistence.

- Writing routes for your resources, and associating them with resources and/or
  ``PhlyRestfully\ResourceController``.

- Writing metadata describing your resources, including what routes to associate
  with them.

All API calls are handled by ``PhlyRestfully\ResourceController``, which in
turn composes a ``PhlyRestfully\Resource`` object and calls methods on it. The
various methods of the controller will return either
``PhlyRestfully\ApiProblem`` results on error conditions, or, on success, a
``PhlyRestfully\HalResource`` or ``PhlyRestfully\HalCollection`` instance; these
are then composed into a ``PhlyRestfully\View\RestfulJsonModel``.

If the MVC detects a ``PhlyRestfully\View\RestfulJsonModel`` during rendering,
it will select ``PhlyRestfully\View\RestfulJsonRenderer``. This, with the help
of the ``PhlyRestfully\Plugin\HalLinks`` plugin, will generate an appropriate
payload based on the object composed, and ensure the appropriate Content-Type
header is used.

If a ``PhlyRestfully\HalCollection`` is detected, and the renderer determines
that it composes a ``Zend\Paginator\Paginator`` instance, the ``HalLinks``
plugin will also generate pagination relational links to render in the payload.

.. _phlyrestfully.basics.resources:

Resources
---------

In order to perist resources or retrieve resources to represent, PhlyRestfully
uses a ``PhlyRestfully\Resource`` instance. This class simply triggers an event
based on the operation requested; you, as a developer, provide and attach
listeners to those events to do the actual work.

``PhlyRestfully\Resource`` defines the following events, with the following
event parameters:

+-------------------+------------------------------+
+ Event name        + Parameters                   +
+===================+==============================+
+ create            + - data                       +
+-------------------+------------------------------+
+ update            + - id                         +
+                   + - data                       +
+-------------------+------------------------------+
+ replaceList       + - data                       +
+-------------------+------------------------------+
+ patch             + - id                         +
+                   + - data                       +
+-------------------+------------------------------+
+ delete            + - id                         +
+-------------------+------------------------------+
+ deleteList        + - data                       +
+-------------------+------------------------------+
+ fetch             + - id                         +
+-------------------+------------------------------+
+ fetchAll          +                              +
+-------------------+------------------------------+

Event listeners receive an instance of ``PhlyRestfully\ResourceEvent``, which
also composes the route matches and query parameters from the request. You may
retrieve them from the event instance using the following methods:

- ``getQueryParams()`` (returns a ``Zend\Stdlib\Parameters`` instance)
- ``getRouteMatch()`` (returns a ``Zend\Mvc\Router\RouteMatch`` instance)
- ``getQueryParam($name, $default = null)``
- ``getRouteParam($name, $default = null)``

Within your listeners, you have the option of throwing an exception in order to
raise an ``ApiProblem`` response. The following maps events to the special
exceptions you can raise; all exceptions are in the ``PhlyRestfully\Exception``
namespace, except where globally qualified:

+-------------------+------------------------------+
+ Event name        + Parameters                   +
+===================+==============================+
+ create            + ``CreationException``        +
+-------------------+------------------------------+
+ update            + ``UpdateException``          +
+-------------------+------------------------------+
+ replaceList       + ``UpdateException``          +
+-------------------+------------------------------+
+ patch             + ``PatchException``           +
+-------------------+------------------------------+
+ delete            + ``\Exception``               +
+-------------------+------------------------------+
+ deleteList        + ``\Exception``               +
+-------------------+------------------------------+
+ fetch             + ``\Exception``               +
+-------------------+------------------------------+
+ fetchAll          + ``\Exception``               +
+-------------------+------------------------------+

Additionally, if you throw any exception implementing
``PhlyRestfully\Exception\ProblemExceptionInterface``, it can be used to seed an
``ApiProblem`` instance with the appropriate information. Such an exception
needs to define the following methods:

- **getAdditionalDetails()**, which should return a string or array.
- **getDescribedBy()**, which should return a URI for the "describedBy" field.
- **getTitle()**, which should return a string for the "title" field.

The exception code and message will be used for the "httpStatus" and "detail",
respectively.

The ``CreationException``, ``UpdateException``, and ``PatchException`` types all
inherit from ``DomainException``, which implements the
``ProblemExceptionInterface``.

As a quick example, let's look at two listeners, one that listens on the
``create`` event, and another on the ``fetch`` event, in order to see how we
might handle them.

.. code-block:: php
    :linenos:

    // listener on "create"
    function ($e) {
        $data = $e->getParam('data');

        // Assume an ActiveRecord-like pattern here for simplicity
        $user = User::factory($data);
        if (!$user->isValid()) {
            $ex = new CreationException('New user failed validation', 400);
            $ex->setAdditionalDetails($user->getMessages());
            $ex->setDescibedBy('http://example.org/api/errors/user-validation');
            $ex->setTitle('Validation error');
            throw $ex;
        }

        $user->persist();
        return $user;
    }

    // listener on "fetch"
    function ($e) {
        $id = $e->getParam('id');

        // Assume an ActiveRecord-like pattern here for simplicity
        $user = User::fetch($id);
        if (!$user) {
            $ex = new DomainException('User not found', 404);
            $ex->setDescibedBy('http://example.org/api/errors/user-not-found');
            $ex->setTitle('User not found');
            throw $ex;
        }

        return $user;
    }

Typically, you will create a ``Zend\EventManager\ListenerAggregateInterface``
implementation that will contain all of your listeners, so that you can also
compose in other classes such as data mappers, a service layer, etc. Read about
`listener aggregates in the ZF2 documentation
<http://zf2.readthedocs.org/en/latest/tutorials/tutorial.eventmanager.html#listener-aggregates>`_
if you are unfamiliar with them.

In a later section, I will show you how to wire your listener aggregate to a
resource and resource controller.

.. _phlyrestfully.basics.controllers:

ResourceControllers
-------------------

While the ``Resource`` hands off work to your domain logic,
``PhlyRestfully\ResourceController`` mediates between the incoming request and
the ``Resource``, as well as ensures an appropriate response payload is created
and returned.

For the majority of cases, you should be able to use the ``ResourceController``
unmodified; you will only need to provide it with a ``Resource`` instance and
some configuration detailing what Content-Types to respond to, what constitutes
an acceptable Accept header, and what HTTP methods are valid for both
collections and individual resources.

A common factory for a ``ResourceController`` instance might look like the
following:

.. code-block:: php
    :linenos:

    return array(
        'controllers' => array(
            'PasteController' => function ($controllers) {
                $services    = $controllers->getServiceLocator();

                $persistence = $services->get('PastePersistenceListener');
                $events      = $services->get('EventManager');
                $events->setIdentifiers('PasteResource`);
                $events->attach($persistence);

                $resource    = new PhlyRestfully\Resource();
                $resource->setEventManager($events);

                $controller = new PhlyRestfully\ResourceController('PasteController`);
                $controller->setResource($resource);
                $controller->setRoute('paste/api');
                $controller->setCollectionName('pastes');
                $controller->setPageSize(30);
                $controller->setCollectionHttpOptions(array(
                    'GET',
                    'POST',
                ));
                $controller->setResourceHttpOptions(array(
                    'GET',
                ));

                return $controller;
            },
        ),
    );

Essentially, three steps are taken:

- A listener is pulled from the service manager, and injected into a new event
  manager instance.
- A ``Resource`` instance is created, and injected with the event manager
  instance.
- A ``ResourceController`` instance is created, and injected with the
  ``Resource`` instance and some configuration.

Considering that most ``ResourceController`` instances follow the same pattern,
PhlyRestfully provides an abstract factory for controllers that does the work
for you. To use it, you will provide a ``resources`` subkey in your
``phlyrestfully`` configuration, with controller name/configuration pairs. As an
example:

.. code-block:: php
    :linenos:

    // In a module's configuration, or the autoloadable configuration of your
    // application:
    return array(
        'phlyrestfully' => array(
            'resources' => array(
                // Key is the service name for the controller; value is
                // configuration
                'MyApi\Controller\Contacts' => array(
                    // Event identifier for the resource controller. By default,
                    // the resource name is used; you can use a different
                    // identifier via this key.
                    // (OPTIONAL)
                    'identifier' => 'Contacts',
    
                    // Name of the service locator key OR the fully qualified
                    // class name of the resource listener (latter works only if
                    // the class has no required arguments in the constructor).
                    // (REQUIRED)
                    'listener'   => 'MyApi\Resource\Contacts',
    
                    // Event identifiers for the composed resource. By default,
                    // the class name of the listener is used; you can add another
                    // identifier, or an array of identifiers, via this key.
                    // (OPTIONAL)
                    'resource_identifiers' => array('ContactsResource'),
    
                    // Accept criteria (which accept headers will be allowed) 
                    // (OPTIONAL)
                    'accept_criteria' => array(
                        'PhlyRestfully\View\RestfulJsonModel' => array(
                            'application/json',
                            'text/json',
                        ),
                    ),
    
                    // HTTP options for resource collections
                    // (OPTIONAL)
                    'collection_http_options' => array('get', 'post'),
    
                    // Collection name (OPTIONAL)
                    'collection_name' => 'contacts',
    
                    // Query parameter or array of query parameters that should be
                    // injected into collection links if discovered in the request.
                    // By default, only the "page" query parameter will be present.
                    // (OPTIONAL)
                    'collection_query_whitelist' => 'sort',
    
                    // Content types to respond to 
                    // (OPTIONAL)
                    'content_type' => array(
                        ResourceController::CONTENT_TYPE_JSON => array(
                            'application/json',
                            'application/hal+json',
                            'text/json',
                        ),
                    ),
    
                    // If a custom identifier_name is used 
                    // (OPTIONAL)
                    'identifier_name'  => 'contact_id',
    
                    // Number of items to return per page of a collection 
                    // (OPTIONAL)
                    'page_size'  => 30,
    
                    // HTTP options for individual resources
                    // (OPTIONAL)
                    'resource_http_options'   => array('get', 'patch', 'put', 'delete'),
    
                    // name of the route associated with this resource
                    // (REQUIRED)
                    'route_name' => 'api/contacts',
                ),
            ),
        ),
    );

The options defined above cover every available configuration option of the
``ResourceController``, and ensure that your primary listener for the
``Resource`` is attached. Additionally, it ensures that both your ``Resource``
and ``ResourceController`` have defined identifiers for their composed event
manager instances, allowing you to attach shared event listeners - which can be
useful for implementing logging, caching, authentication and authorization
checks, etc.

Note that the above configuration assumes that you are defining a
``Zend\EventManager\ListenerAggregateInterface`` implementation to attach to the
``Resource``. This is a good practice anyways, as it keeps the logic
encapsulated, and allows you to have stateful listeners -- which is particularly
useful as most often you will consume a mapper or similar within your listeners
in order to persist resources or fetch resources from persistence.

.. _phlyrestfully.basics.example:

Example
-------

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
