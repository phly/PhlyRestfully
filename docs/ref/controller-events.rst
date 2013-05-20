.. _ref/controller-events:

Controller Events
=================

Each of the various REST endpoint methods - ``create()``, ``delete()``,
``deleteList()``, ``get()``, ``getList()``, ``patch()``, ``update()``, and ``replaceList()``
\- trigger both a ``{methodname}.pre`` and a ``{methodname}.post`` event. 

The "pre" event is executed after validating arguments, and will receive any
arguments passed to the method; the "post" event occurs right before returning
from the method, and receives the same arguments, plus the resource or
collection, if applicable.

These methods are useful in the following scenarios:

- Specifying custom HAL links
- Aggregating additional request parameters to pass to the resource object

As an example, if you wanted to add a "describedby" HAL link to every resource
or collection returned, you could do the following:

.. code-block:: php
    :linenos:

    // Methods we're interested in
    $methods = array(
        'create.post',
        'get.post',
        'getList.post',
    );

    // Assuming $sharedEvents is a ZF2 SharedEventManager instance
    $sharedEvents->attach('Paste\ApiController', $methods, function ($e) {
        $resource = $e->getParam('resource', false);
        if (!$resource) {
            $resource = $e->getParam('collection', false);
        }
    
        if (!$resource instanceof \PhlyRestfully\LinkCollectionAwareInterface) {
            return;
        }
    
        $link = new \PhlyRestfully\Link('describedby');
        $link->setRoute('paste/api/docs');
        $resource->getLinks()->add($link);
    });

.. index:: event, controller, resource controller
