.. _basics.index:

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
