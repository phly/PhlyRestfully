PhlyRestfully: ZF2 Module for JSON REST Services
================================================

This module provides structure and code for quickly implementing RESTful APIs
that use JSON as a transport.

It allows you to create RESTful JSON APIs that use the following standards:

- [HAL](http://tools.ietf.org/html/draft-kelly-json-hal-03), used for creating
  hypermedia links
- [Problem API](http://tools.ietf.org/html/draft-nottingham-http-problem-02),
  used for reporting API problems

Resources
---------

A generic Resource class is provided, which provides the following operations:

- `create($data)`
- `update($id, $data)`
- `patch($id, $data)`
- `delete($id)`
- `fetch($id)`
- `fetchAll()`

Each method:

- Ensures the incoming arguments are well-formed (primarily that `$data`
  is an `array` or `object`)
- Triggers an event with the incoming data
- Pulls the last event listener result, and ensures it is well-formed for the
  operation (typically, returns an `array` or `object`; in the case of
  `delete`, looks for a `boolean`; in the case of `fetchAll`, looks for an
  `array` or `Traversable`)

As such, your job is primarily to create a `ListenerAggregate` for the Resource
which performs the actual persistence operations.

Controller
----------

A generic ResourceController is provided which intercepts incoming requests
and passes data to the Resource. It then inspects the result to generate an
appropriate response.

In cases of errors, a Problem API response payload is generated.

When an item or collection is returned, a HAL payload is generated with
appropriate links.

In all cases, appropriate HTTP response status codes are generated.

The controller expects you to inject the following:

- Resource
- Route name that resolves to the resource
- HTTP OPTIONS the service is allowed to respond to (optional; by default,
  allows delete, get, head, patch, post, and put requests)
- Page size (optional; for paginated results. Defaults to 30.)
- `ServerUrl` view helper (optional; will lazy-instantiate if not found.)

Tying it Together
-----------------

You will need to create at least one factory, and potentially several.

Absolutely required is a unique controller factory for the
ResourceController. As noted in the previous section, you will have to inject
several dependencies. These may be hard-coded in your factory, or pulled as, or 
from, other services.

As a quick example:

```php
'PasteController' => function ($controllers) {
    $services   = $controllers->getServiceLocator();
    $events     = $services->get('EventManager');
    $listener   = new PasteResourceListener(new PasteMongoAdapter);
    $resource   = new PhlyRestfully\Resource();
    $resource->setEventManager($events);
    $events->attach($listener);

    $controller = new PhlyRestfully\ResourceController();
    $controller->setResource($resource);
    $controller->setRoute('paste/api');
    $controller->setHttpOptions(array(
        'GET',
        'HEAD',
        'POST',
    ));
    return $controller;
}
```

The above example instantiates a listener directly, and attaches it to the
event manager instance of a new Resource intance. That resource instance then
is attached to a new ResourceController instance, and the route and HTTP
OPTIONS are provided. Finally, the controller instance is returned.

Routes
------

You should create a segment route for each resource that looks like the
following:

```
/path/to/resource[/[:id]]
```

This single route will then be used for all operations.


TODO
----

- Renderer does not take into account object items
