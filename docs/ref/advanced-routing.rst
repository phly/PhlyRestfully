.. _ref/advanced-routing:

Advanced Routing
================

The recommended route for a resource is a ``Zend\Mvc\Router\Http\Segment``
route, with an identifier:

.. code-block:: php

    'route' => '/resource[/:id]'

This works great for standalone resources, but poses a problem for hierarchical
resources. As an example, if you had a "users" resource, but then had
"addresses" that were managed as part of the user, the following route
definition poses a problem:

.. code-block:: php
    :linenos:

    'users' => array(
        'type' => 'Segment',
        'options' => array(
            'route' => '/users[/:id]',
            'controller' => 'UserResourceController',
        ),
        'may_terminate' => true,
        'child_routes' => array(
            'type' => 'Segment',
            'options' => array(
                'route' => '/addresses[/:id]',
                'controller' => 'UserAddressResourceController',
            ),
        ),
    ),

Spot the problem? Both the parent and child have an "id" segment, which means
there is a conflict. Let's refactor this a bit:

.. code-block:: php
    :linenos:

    'users' => array(
        'type' => 'Segment',
        'options' => array(
            'route' => '/users[/:user_id]',
            'controller' => 'UserResourceController',
        ),
        'may_terminate' => true,
        'child_routes' => array(
            'type' => 'Segment',
            'options' => array(
                'route' => '/addresses[/:address_id]',
                'controller' => 'UserAddressResourceController',
            ),
        ),
    ),

Now we have a new problem, or rather, two new problems: by default, the
``ResourceController`` uses "id" as the identifier, and this same identifier
name is used to generate URIs. How can we change that?

First, the ``ResourceController`` allows you to define the identifier name for
the specific resource being exposed. You can do this via the
``setIdentifierName()`` method, but more commonly, you'll handle it via the
``identifier_name`` configuration parameter:

.. code-block:: php
    :linenos:

    'phlyrestfully' => array(
        'resources' => array(
            'UserResourceController' => array(
                // ...
                'identifier_name' => 'user_id',
                // ...
            ),
            'UserAddressResourceController' => array(
                // ...
                'identifier_name' => 'address_id',
                // ...
            ),
        ),
    ),

If you are rendering child resources as part of a resource, however, you need to
hint to the renderer about where to look for an identifier.

There are several mechanisms for this: the ``getIdFromResource`` and
``createLink`` events of the ``PhlyRestfully\Plugin\HalLinks`` plugin; or
:ref:`a metadata map <ref/metadata-map>`.

The ``HalLinks`` events are as followed, and triggered by the methods specified:

+---------------------------+-----------------------+-------------------------+
| Event name                | Method triggering     | Parameters              |
|                           | event                 |                         |
+===========================+=======================+=========================+
| createLink                | ``createLink``        | - route :sup:`*`        |
|                           |                       | - id                    |
|                           |                       | - resource              |
|                           |                       | - params :sup:`*`       |
+---------------------------+-----------------------+-------------------------+
| getIdFromResource         | ``getIdFromResource`` | - resource :sup:`*`     |
+---------------------------+-----------------------+-------------------------+

Let's dive into each of the specific events.

.. note::

    In general, you shouldn't need to tie into the events listed on this page
    very often. The recommended way to customize URL generation for resources is
    to instead use :ref:`a metadata map <ref/metadata-map>`. 

createLink event
----------------

The ``createLink`` method is currently called only from
``PhlyRestfully\ResourceController::create()``, and is used to generate the
``Location`` header. Essentially, what it does is call the ``url()`` helper with
the passed route, and the ``serverUrl()`` helper with that result to generate a
fully-qualified URL.

If passed a resource identifier and resource, you can attach to the event the
method triggers in order to modifiy the route parameters and/or options when
generating the link.

Consider the following scenario: you need to specify an alternate routing
parameter to use for the identifier, and you want to use the "user" associated
with the resource as a route parameter. Finally, you want to change the route
used to generate this particular URI.

The following will do that:

.. code-block:: php
    :linenos:

    $request = $services->get('Request');
    $sharedEvents->attach('PhlyRestfully\Plugin\HalLinks', 'createLink', function ($e) use ($request) {
        $resource = $e->getParam('resource');
        if (!$resource instanceof Paste) {
            // only react for a specific type of resource
            return;
        }

        // The parameters here are an ArrayObject, which means we can simply set
        // the values on it, and the method calling us will use those.
        $params = $e->getParams();

        $params['route'] = 'paste/api/by-user';

        $id   = $e->getParam('id');
        $user = $resource->getUser();
        $params['params']['paste_id'] = $id;
        $params['params']['user_id']  = $user->getId();
    }, 100);

The above listener will change the route used to "paste/api/by-user", and ensure
that the route parameters "paste_id" and "user_id" are set based on the resource
provided.

The above will be called with ``create`` is successful. Additionally, you can
use the ``HalLinks`` plugin from other listeners or your view layer, and call
the ``createLink()`` method manually -- which will also trigger any listeners.

getIdFromResource event
-----------------------

The ``getIdFromResource`` event is only indirectly related to routing. Its
purpose is to retrieve the identifier for a given resource so that a "self"
relational link may be generated; that is its sole purpose.

The event receives exactly one argument, the resource for which the identifier
is needed. A default listener is attached, at priority 1, that uses the
following algorithm:

- If the resource is an array, and an "id" key exists, it returns that value.
- If the resource is an object and has a public "id" property, it returns that
  value.
- If the resource is an object, and has a public ``getId()`` method, it returns
  the value returned by that method.

In all other cases, it returns a boolean ``false``, which generally results in
an exception or other error.

This is where you, the developer come in: you can write a listener for this
event in order to return the identifier yourself.

As an example, let's consider the original example, where we have "user" and
"address" resources. If these are of specific types, we could write listeners
like the following:

.. code-block:: php
    :linenos:

    $sharedEvents->attach('PhlyRestfully\Plugin\HalLinks', 'getIdFromResource', function ($e) {
        $resource = $e->getParam('resource');
        if (!$resource instanceof User) {
            return;
        }
        return $resource->user_id;
    }, 100);

    $sharedEvents->attach('PhlyRestfully\Plugin\HalLinks', 'getIdFromResource', function ($e) {
        $resource = $e->getParam('resource');
        if (!$resource instanceof UserAddress) {
            return;
        }
        return $resource->address_id;
    }, 100);

Since writing listeners like these gets old quickly, I recommend using :ref:`a
metadata map <ref/metadata-map>` instead.

.. index:: event, resource controller, hal, routing, HalLinks, metadata
