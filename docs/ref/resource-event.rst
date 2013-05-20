.. _ref.resource-event:

The ResourceEvent
=================

When ``PhlyRestfully\Resource`` triggers events, it passes a custom event type,
``PhlyRestfully\ResourceEvent``. This custom event contains several additional
methods which allow you to access route match and query parameters, which are
often useful when working with child routes or wanting to provide sorting,
filtering, or other actions on collections.

The available methods are:

- ``getRouteMatch()``, which returns the ``Zend\Mvc\Router\RouteMatch`` instance
  that indicates the currently active route in the MVC, and contains any
  parameters matched during routing.
- ``getRouteParam($name, $default = null)`` allows you to retrieve a single route
  match parameter.
- ``getQueryParams()`` returns the collection of query parameters from the current
  request.
- ``getQueryParam($name, $default = null)`` allows you to retrieve a single query
  parameter.

The ``ResourceEvent`` is created internal to the ``Resource``, and cloned for
each event triggered. If you would like to pass additional parameters, the
``Resource`` object allows this, via its ``setEventParams()`` method, which
accepts an associative array of named parameters.

As an example, if you were handling authentication via a custom HTTP header, you
could pull this in a listener, and pass it to the resource as follows; the
following is the body of a theoretical ``onBootstrap()`` method of your
``Module`` class.

.. code-block:: php
    :linenos:

    $target       = $e->getTarget();
    $events       = $target->getEventManager();
    $sharedEvents = $events->getSharedManager();
    $sharedEvents->attach('Paste\ApiController', 'create', function ($e) {
        $request  = $e->getRequest();
        $headers  = $request->getHeaders();

        if (!$headers->has('X-Paste-Authentication')) {
            return;
        }

        $auth     = $headers->get('X-Paste-Authentication')->getFieldValue();
        $target   = $e->getTarget();
        $resource = $target->getResource();

        $resource->setEventParams(array(
            'auth' => $auth,
        ));
    }, 100);

The above grabs the header, if it exists, and passes it into the resource as an
event parameter. Later, in a listener, you can grab it:

.. code-block:: php
    :linenos:

    $auth = $e->getParam('auth', false);

.. index:: event, resource, resource listener, resource event
