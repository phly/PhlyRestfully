.. _ref/metadata-map:

Metadata Mapping
================

If you have been reading the reference guide sequentially, almost every page has
referred to this one at some point. The reason is that, for purposes of
flexibility, PhlyRestfully has needed to provide a low-level, configurable
mechanism that solves the problems of:

- ensuring resources have the correct "self" relational link
- ensuring resources are extracted to a JSON representation correctly
- ensuring that embedded resources are rendered as embedded HAL resources

To achieve this in a simpler fashion, PhlyRestfully provides the ability to
create a "metadata map." The metadata map maps a class to a set of "rules" that
define whether the class represents a resource or collection, the information
necessary to generate a "self" relational link, and a hydrator to use to extract
the resource.

This metadata map is defined via configuration. Let's consider the example from
the :ref:`embedded resources section <ref/embedding-resources>`:

.. code-block:: php
    :linenos:

    return array(
        'phlyrestfully' => array(
            'metadata_map' => array(
                'User' => array(
                    'hydrator'        => 'ObjectProperty',
                    'identifier_name' => 'id',
                    'route'           => 'api/user',
                ),
                'Url' => array(
                    'hydrator'        => 'ObjectProperty',
                    'route'           => 'api/user/url',
                    'identifier_name' => 'url_id',
                ),
                'Phones' => array(
                    'is_collection'   => true,
                    'route'           => 'api/user/phone',
                ),
                'Phone' => array(
                    'hydrator'        => 'ObjectProperty',
                    'route'           => 'api/user/phone',
                    'identifier_name' => 'phone_id',
                ),
            ),
        ),
    );

Essentially, the map allows you to associate metadata about how the
representation of a resource.

Metadata options
----------------

The following options are available for metadata maps:

- **hydrator**: the fully qualified class name of a hydrator, or a service name
  ``Zend\Stdlib\Hydrator\HydratorPluginManager`` recognizes,  to use to extract
  the resource. (**OPTIONAL**)
- **identifier_name**: the resource parameter corresponding to the identifier;
  defaults to "id". (**OPTIONAL**)
- **is_collection**: boolean flag indicating whether or not the resource is a
  collection; defaults to "false". (**OPTIONAL**)
- **resource_route**: the name of the route to use for resources embedded as part
  of a collection. If not set, the route for the resource is used. (**OPTIONAL**)
- **route**: the name of the route to use for generating the "self" relational
  link. (**OPTIONAL**; this or ``url`` **MUST** be set, however)
- **route_options**: any options to pass to the route when generating the "self"
  relational link. (**OPTIONAL**)
- **route_params**: any route match parameters to pass to the route when
  generating the "self" relational link. (**OPTIONAL**)
- **url**: the specific URL to use with this resource. (**OPTIONAL**; this or ``route``
  **MUST** be set, however)

Collections
-----------

If you paid careful attention to the example, you'll note that there is one
additional type in the definition, ``Phones``. When creating metadata for a
collection, you need to define a first-class type so that ``HalLinks`` can match
the collection against the metadata map. This is generally regarded as a best
practice when doing `domain modeling
<http://en.wikipedia.org/wiki/Domain_model>`_; a type per collection makes it
easy to understand what types of objects the collection contains, and allows for
domain-specific logic surrounding the collection.

However, that poses some problems if you want to :ref:`paginate your collection
<ref/collections-and-pagination>`, as instances of ``Zend\Paginator\Paginator``
are identified by ``HalLinks`` when rendering collections in order to create
appropriate relational links.

The solution to that is to create an empty extension of ``Paginator``:

.. code-block:: php
    :linenos:

    use Zend\Paginator\Paginator;

    class Phones extends Paginator
    {
    }

.. index:: resource, collection, pagination, HalLinks, hal, metadata
