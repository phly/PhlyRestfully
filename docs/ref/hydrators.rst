.. _ref/hydrators:

Hydrators
=========

``Zend\Stdlib\Hydrator`` offers a general-purpose solution for mapping arrays to
objects (hydration) and objects to arrays (extraction). In PhlyRestfully,
hydrators are used during rendering for this second operation, extraction, so
that resources may be represented via JSON.

Within PhlyRestfully, ``PhlyRestfully\View\JsonRenderer`` delegates to
``PhlyRestfully\Plugin\HalLinks`` in order to return a representation of a
resource or collection. This was done to allow you, the user, to override how
rendering is accomplished if desired; you can extend the ``HalLinks`` plugin and
register your own version as a controller plugin and view helper.

Since ``HalLinks`` handles the conversion, it also acts as a registry for
mapping classes to the hydrators responsible for extracting them.

Manually working with the hydrator map
--------------------------------------

If you want to programmatically associate classes with their hydrators, you can
grab an instance of the ``HalLinks`` plugin in several ways:

- via the view helper manager
- via the controller plugin manager

To extract it from the view helper manager:

.. code-block:: php
    :linenos:

    // Assuming we're in a module's onBootstrap method or other listener on an
    // application event:
    $app = $e->getApplication();
    $services = $app->getServiceManager();
    $helpers  = $services->get('ViewHelperManager');
    $halLinks = $helpers->get('HalLinks');

Similarly, you can grab it from the controller plugin manager:

.. code-block:: php
    :linenos:

    // Assuming we're in a module's onBootstrap method or other listener on an
    // application event:
    $app = $e->getApplication();
    $services = $app->getServiceManager();
    $plugins  = $services->get('ControllerPluginManager');
    $halLinks = $plugins->get('HalLinks');

Alternately, if listening on a controller event, pull it from the controller's
``plugin()`` method:

.. code-block:: php
    :linenos:

    $controller = $e->getTarget();
    $halLinks   = $controller->plugin('HalLinks');

Once you have the plugin, you can register class/hydrator pairs using the
``addHydrator()`` method:

.. code-block:: php
    :linenos:

    // Instantiate the hydrator instance directly:
    $hydrator = new \Zend\Stdlib\Hydrator\ClassMethods();

    // Or pull it from the HydratorManager:
    $hydrators = $services->get('HydratorManager');
    $hydrator  = $hydrators->get('ClassMethods');

    // Then register it:
    $halLinks->addHydrator('Paste\PasteResource', $hydrator);

    // More succintly, since HalLinks composes the HydratorManager by default,
    // you can use te short name of the hydrator service:
    $halLinks->addHydrator('Paste\PasteResource', 'ClassMethods');

All done!

You can also specify a default hydrator to use, if ``HalLinks`` can't find the
resource class in the map:

.. code-block:: php

    $halLinks->setDefaultHydrator($hydrator);

However, it's a lot of boiler plate code. There is a simpler way: configuration.

Configuration-driven hydrator maps
----------------------------------

You can specify hydrators to use with the objects you return from your resources
via configuration, and you can specify both a map of class/hydrator service
pairs as well as a default hydrator to use as a fallback. As an example,
consider the following `config/autoload/phlyrestfully.global.php` file:

.. code-block:: php
    :linenos:

    return array(
        'phlyrestfully' => array(
            'renderer' => array(
                'default_hydrator' => 'ArraySerializable',
                'hydrators' => array(
                    'My\Resources\Foo' => 'ObjectProperty',
                    'My\Resources\Bar' => 'Reflection',
                ),
            ),
        ),
    );

The above specifies ``Zend\Stdlib\Hydrator\ArraySerializable`` as the default
hydrator, and maps the ``ObjectProperty`` hydrator to the ``Foo`` resource, and the
``Reflection`` hydrator to the ``Bar`` resource. Note that the short name for
the hydrator is used; ``HalLinks`` composes the ``HydratorManager`` service by
default, and pulls hydrators from there if provided by service name.

This is a cheap and easy way to ensure that you can extract your resources to
arrays to be used as JSON representations.

.. index:: hydrator, resource, HalLinks
