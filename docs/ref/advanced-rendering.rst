.. _ref/advanced-rendering:

Advanced Rendering
==================

What if you're not returning an array as a resource from your persistence layer?
Somehow, you have to be able to transform it to an associative array so that it
can be rendered to JSON easily. There are a variety of ways to do this,
obviously; the easiest would be to make your resource implement
``JsonSerializable``. If that's not an option, though, what other approaches do
you have?

In this section, we'll explore one specific solution that is the most explicit
of those available: the ``renderCollection.resource`` event of the ``HalLinks``
plugin.

HalLinks overview
-----------------

``PhlyRestfully\Plugin\HalLinks`` acts as both a controller plugin as well as a
view helper. In most cases, you likely will not interact directly with it.
However, it does expose a few pieces of functionality that may be of interest:

- The ``createLink()`` method, which is handy for creating fully-qualified
  URLs (i.e., contains schema, hostname, and port, in addition to path). This
  was detailed :ref:`in the previous section <ref/advanced-routing>`.
- The ``getIdFromResource`` event (also detailed :ref:`in the previous section
  <ref/advanced-routing>`).
- The ``renderResource`` and ``renderCollection`` events.
- The ``renderCollection.resource`` event.

If in a controller, or interacting with a controller instance, you can access it
via the controller's ``plugin()`` method:

.. code-block:: php

    $halLinks = $controller->plugin('HalLinks');

For the purposes of this chapter, we'll look specifically at the
``renderResource``, ``renderCollection``, and ``renderCollection.resource``
events, as they allow you, the developer, to fully customize how you extract
your resource to an array as well as manipulate links.

The renderResource and renderCollection events
----------------------------------------------

These events are triggered each time you render a resource or collection, even
if they are embedded. As such, this allows you to introspect these items and
manipulate them prior to extracting them to a representation.

As an example, let's say we want to inject a "describedby" link into any
``My\User`` resource and/or ``My\Users`` collection. We can do this as follows:

.. code-block:: php
    :linenos:

    $sharedEvents->attach(
        'PhlyRestfully\Plugin\HalLinks',
        array('renderResource', 'renderCollection'),
        function ($e) {
            $resource   = $e->getParam('resource', false);
            $collection = $e->getParam('collection', false);
            if (!$resource && !$collection) {
                return;
            }
            if ($resource && !$resource instanceof \My\User) {
                return;
            }
            if ($collection && !$collection instanceof \My\Users) {
                return;
            }
            if ($collection) {
                $resource = $collection;
            }
            $links = $resource->getLinks();
            $links->add(\PhlyRestfully\Link::factory(array(
                'rel' => 'describedby',
                'url' => 'http://example.com/api/help/resources/user',
            )));
        }
    );

The above attaches to both events, and then checks if we have a resource or
collection we're interested in; if so, it creates a link and injects it into the
composed link collection. This will ensure we have a "describedby" relational
link in each one of these rendered resources.

The renderCollection.resource event
-----------------------------------

This method is triggered as part of the ``renderCollection()`` method, once for
each resource in the collection. It receives the following parameters:

- **collection**, the current collection being iterated
- **resource**, the current resource in the iteration
- **route**, the resource route defined for the collection; usually, this is the
  same route as provided to the controller.
- **routeParams**, any route params defined for resources within the collection;
  usually, this is empty.
- **routeOptions**, any route options defined for resources within the collection;
  usually, this is empty.

Let's consider the following scenario: we're rendering something like a public
status timeline, but the individual status resources in our timeline belong to
another route. Additionally, we want to show a subset of information for each
individual status when in the public timeline; we don't need the full status
resource.

We'd define a listener:

.. code-block:: php
    :linenos:

    $sharedEvents->attach('PhlyRestfully\Plugin\HalLinks', 'renderCollection.resource', function ($e) {
        $collection = $e->getParam('collection');
        if (!$collection instanceof PublicTimeline) {
            // nothing to do here
            return;
        }

        $resource = $e->getParam('resource');
        if (!$resource instanceof Status) {
            // nothing to do here
            return;
        }

        $return = array(
            'id'        => $resource->getId(),
            'user'      => $resource->getUser(),
            'timestamp' => $resource->getTimestamp(),
        );

        // Parameters are stored as an ArrayObject, allowing us to change them
        // in situ
        $params = $e->getParams();
        $params['resource']    = $return;
        $params['route']       = 'api/status/by-user';
        $params['routeParams'] = array(
            'id'   => $resource->getId(),
            'user' => $resource->getUser(),
        );
    }, 100);

The above extracts three specific fields of the ``Status`` object and creates an
array representation for them. Additionally, it changes the route used, and sets
some route parameters. This information will be used when generating a "self"
relational link for the resource, and the newly created array will be used when
creating the representation for the resource itself.

This approach gives us maximum customization during the rendering process, but
comes at the cost of added boiler plate code. As per the section on routing, I
recommend using :ref:`a metadata map <ref/metadata-map>` unless you need to
dynamically determine route parameters or filter the resource before rendering.
Additionally, in many cases :ref:`hydrators <ref/hydrators>` (the subject of the
next section) are more than sufficient for the purpose of creating an array
representation of your resource.

.. index:: event, HalLinks, hydrator, metadata, resource
