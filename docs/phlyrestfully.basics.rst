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
