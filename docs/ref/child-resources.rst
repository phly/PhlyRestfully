.. _ref/child-resources:

Child Resources
===============

Resources often do not exist in isolation. Besides some resources embedding
others, in some cases, a resource exists only as a result of another resource
existing -- in other words, within a hierarchical or tree structure. Such
resources are often given the name "child resources."

In the :ref:`advanced routing chapter <ref/advanced-routing>`, we looked at one
such example, with a user and addresses.

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
            'addresses' => array(
                'type' => 'Segment',
                'options' => array(
                    'route' => '/addresses[/:address_id]',
                    'controller' => 'UserAddressResourceController',
                ),
            ),
        ),
    ),

In that chapter, I looked at how to tie into various events in order to alter
routing parameters, which would ensure that the relational URLs were generated
correctly. I also noted that there's a better approach: :ref:`metadata maps
<ref/metadata-map>`. Let's look at such a solution now.

First, let's make some assumptions:

- Users are of type ``User``, and can be hydrated using the ``ClassMethods``
  hydrator.
- Individual addresses are of type ``UserAddress``, and can by hydrated using
  the ``ObjectProperty`` hydrator.
- Address collections have their own type, ``UserAddresses``.
- The users collection is called "users"
- The address collection is called "addresses"
- The class ``UserListener`` listens on ``Resource`` events for users.
- The class ``UserAddressListener`` listens on ``Resource`` events for addresses.

Now, let's create some resource controllers, using configuration as noted in the
chapter on :ref:`resource controllers <basics.controllers>`.

.. code-block:: php
    :linenos:

    return array(
        // ...
        'phlyrestfully' => array(
            'resources' => array(
                'UserResourceController' => array(
                    'listener'                => 'UserListener',
                    'collection_name'         => 'users',
                    'collection_http_options' => array('get', 'post'),
                    'resource_http_options'   => array('get', 'patch', 'put', 'delete'),
                    'page_size'               => 30,
                ),
                'UserAddressResourceController' => array(
                    'listener'                => 'UserAddressListener',
                    'collection_name'         => 'addresses',
                    'collection_http_options' => array('get', 'post'),
                    'resource_http_options'   => array('get', 'patch', 'put', 'delete'),
                ),
            ),
        ),
    );

Now we have controllers that can respond properly. Let's now configure the
metadata and hydrator maps for our resources.

.. code-block:: php
    :linenos:

    return array(
        // ...
        'service_manager' => array(
            // ...
            'invokables' => array(
                'Hydrator\ClassMethods'   => 'Zend\Stdlib\Hydrator\ClassMethods',
                'Hydrator\ObjectProperty' => 'Zend\Stdlib\Hydrator\ObjectProperty',
            ),
        ),
        'phlyrestfully' => array(
            // ...
            'renderer' => array(
                'hydrators' => array(
                    'User'        => 'Hydrator\ClassMethods',
                    'UserAddress' => 'Hydrator\ObjectProperty',
                ),
            ),
            'metadata_map' => array(
                'User' => array(
                    'identifier_name' => 'user_id',
                    'route'           => 'users',
                ),
                'UserAddress' => array(
                    'identifier_name' => 'address_id',
                    'route'           => 'users/addresses',
                ),
                'UserAddresses' => array(
                    'identifier_name' => 'address_id',
                    'route'           => 'users/addresses',
                    'is_collection'   => true,
                    'route_options'   => array('query' => true),
                ),
            ),
        ),
    );

Now, when we render a ``User``, if it composes a ``UserAddresses`` object, that
object will be rendered as an embedded collection, and each resource inside it
will be rendered using the appropriate route and identifier.

.. index:: hydrator, metadata, collection, resource, controller
