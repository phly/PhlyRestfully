.. _basics.controllers:

ResourceControllers
===================

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
                    // Name of the controller class to use, if other than
                    // PhlyRestfully\ResourceController. Must extend
                    // PhlyRestfully\ResourceController, however, to be valid.
                    // (OPTIONAL)
                    'controller_class' => 'PhlyRestfully\ResourceController',

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

.. index:: controller, resource, resource controller, resource listener, options
