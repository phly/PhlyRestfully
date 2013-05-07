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
- `replaceList($data)`
- `patch($id, $data)`
- `delete($id)`
- `deleteList($data = null)`
- `fetch($id)`
- `fetchAll()`

Each method:

- Ensures the incoming arguments are well-formed (primarily that `$data`
  is an `array` or `object`)
- Triggers an event with the incoming data
- Pulls the last event listener result, and ensures it is well-formed for the
  operation (typically, returns an `array` or `object`; in the case of
  `delete` and `deleteList`, looks for a `boolean`; in the case of `fetchAll`,
  looks for an `array` or `Traversable`)

As such, your job is primarily to create a `ListenerAggregate` for the Resource
which performs the actual persistence operations.

The event provided to the lister is an instance of
`PhlyRestfully\ResourceEvent`. This event subclass provides several extra
methods that allow you to access route and query parameters (often useful when
working with child resources and collections, respectively):

- `getRouteMatch()` returns the `Zend\Mvc\Router\RouteMatch` instance that
  indicates the currently active route in the MVC.
- `getRouteParam($name, $default = null)` allows you to retrieve a single route
  match parameter.
- `getQueryParams()` returns the collection of query parameters from the current
  request.
- `getQueryParam($name, $default = null)` allows you to retrieve a single query
  parameter.

Controller
----------

A generic ResourceController is provided which intercepts incoming requests
and passes data to the Resource. It then inspects the result to generate an
appropriate response.

In cases of errors, an Problem API response payload is generated.

When a resource or collection is returned, a HAL payload is generated with
appropriate links.

In all cases, appropriate HTTP response status codes are generated.

The controller expects you to inject the following:

- Resource
- Route name that resolves to the resource
- Event identifier for allowing attachment via the shared event manager; this
  is passed via the constructor (optional; by default, listens to
  `PhlyRestfully\ResourceController`)
- "Accept" criteria for use with the `AcceptableViewModelSelector` (optional;
  by default, assigns any `*/json` requests to the `RestfulJsonModel`)
- HTTP OPTIONS the service is allowed to respond to, for both collections and
  individual resources (optional; head and options are always allowed; by default,
  allows get and post requests on collections, and delete, get, patch, and put
  requests on resources)
- Page size (optional; for paginated results. Defaults to 30.)

Tying it Together
-----------------

There are two ways to tie it all together. One uses the included abstract
factory `PhlyRestfully\Factory\ResourceControllerFactory`, and the other is a
more manual process.

Assuming you have a resource listener and route defined, you can use the
`resources` key of the `phlyrestfully` configuration to define resource
controllers as follows:

```php
// In a module's configuration, or the autoloadable configuration of your
// application:
return array(
    'phlyrestfully' => array(
        'resources' => array(
            // Key is the service name for the controller; value is configuration
            'MyApi\Controller\Contacts' => array(
                // Event identifiers for the resource controller. By default,
                // the resource name is used; you can add another identifier, or
                // an array of identifiers, via this key. (OPTIONAL)
                'identifiers' => array('Contacts', 'MyApi'),

                // name of the service locator key of the resource listener
                'listener'   => 'MyApi\Resource\Contacts',

                // Accept criteria (which accept headers will be allowed) (OPTIONAL)
                'accept_criteria' => array(
                    'PhlyRestfully\View\RestfulJsonModel' => array(
                        'application/json',
                        'text/json',
                    ),
                ),
    
                // HTTP options for resource collections (OPTIONAL)
                'collection_http_options' => array('get', 'post'),
    
                // Collection name (OPTIONAL)
                'collection_name' => 'contacts',

                // Content types to respond to (OPTIONAL)
                'content_type' => array(
                    ResourceController::CONTENT_TYPE_JSON => array(
                        'application/json',
                        'application/hal+json',
                        'text/json',
                    ),
                ),

                // if a custom identifier_name is used (OPTIONAL)
                'identifier_name'  => 'contact_id',

                // Number of items to return per page of a collection (OPTIONAL)
                'page_size'  => 30,

                // HTTP options for individual resources (OPTIONAL)
                'resource_http_options'   => array('get', 'patch', 'put', 'delete'),
    
                // name of the route associated with this resource (REQUIRED)
                'route_name' => 'api/contacts',
            ),
        ),
    ),
);
```

As noted, the `listener` and `route_name` keys are the only required
configuration; provide the other keys as needed. This is probably the easiest
way to define resource controllers, and will serve easily 90% of use cases.

If you want to customize controller instantiation, you can provide your own
factory, just as you would for any other controller. As an example:

```php
'PasteController' => function ($controllers) {
    $services   = $controllers->getServiceLocator();
    $events     = $services->get('EventManager');
    $listener   = new \PasteResourceListener(new PasteMongoAdapter);
    $resource   = new \PhlyRestfully\Resource();
    $resource->setEventManager($events);
    $events->attach($listener);

    $controller = new \PhlyRestfully\ResourceController();
    $controller->setResource($resource);
    $controller->setRoute('paste/api');
    $controller->setCollectionHttpOptions(array(
        'GET',
        'POST',
    ));
    $controller->setResourceHttpOptions(array(
        'GET',
    ));
    return $controller;
}
```

The above example instantiates a listener directly, and attaches it to the
event manager instance of a new Resource intance. That resource instance then
is attached to a new `ResourceController` instance, and the route and HTTP
OPTIONS are provided. Finally, the controller instance is returned.

Routes
------

You should create a segment route for each resource that looks like the
following:

```
/path/to/resource[/[:id]]
```

This single route will then be used for all operations.

If you want to use a route with more segments, and ensure that all captured
segments are present when generating the URL, you will need to hook into the
`HalLinks` plugin. As an example, let's consider the following route:

```
/api/status/:user[/:id]
```

The "user" segment is required, and should always be part of the URL. However,
by default, the `ResourceController` and the `RestfulJsonRenderer` will not have
knowledge of matched route segments, and will not tell the `url()` helper to
re-use matched parameters.

`HalLinks`, however, allows you to attach to its `renderCollection.resource`
event, which gives you the opportunity to provide route parameters for resources
that are part of a collection. As an example, consider the following listeners:

```php
$user    = $matches->getParam('user');
$helpers = $services->get('ViewHelperManager');
$links   = $helpers->get('HalLinks');
$links->getEventManager()->attach('renderCollection.resource', function ($e) use ($user) {
    $params = $e->getParams();
    $params['routeParams']['user'] = $user;
});
```

The above would likely happen in a post-routing listener, where we know we
routed to a specific controller, and can have access to the route matches.
It retrieves the "user" parameter from the route first. Then it retrieves the
`HalLinks` plugin from the view helpers, and attaches to its
`renderCollection.resource` event; the listener simply assigns the user to the
routing parameters -- which are then passed to the `url()` helper when creating
a link.


Collections
-----------

Collections are resources, too, which means they may hold more than simply the
set of resources they encapsulate.

By default, the `ResourceController` simply returns a `HalCollection` with the
collection of resources; if you are using a paginator for the collection, it will
also set the current page and number of items per page to render.

You may want to name the collection of resources you are representing. By default,
we use "items" as the name; you should use a semantic name. This can be done
by either directly setting the collection name on the `HalCollection` using the
`setCollectionName()` method, or calling the same method on the controller.

You can also set additional attributes. This can be done via a listener;
typically, a post-dispatch listener, such as the following, would be a
reasonable time to alter the collection instance. In the following, we update
the collection to include the count, number per page, and type of objects
in the collection.

```php
$events->attach('dispatch', function ($e) {
    $result = $e->getResult();
    if (!$result instanceof RestfulJsonModel) {
        return;
    }
    if (!$result->isHalCollection()) {
        return;
    }
    $collection = $result->getPayload();
    $paginator  = $collection->collection;
    $collection->setAttributes(array(
        'count'         => $paginator->getTotalItemCount(),
        'per_page'      => $collection->pageSize,
        'resource_type' => 'status',
    ));
}, -1);
```

Many APIs may use query string parameters to allow things like sorting,
grouping, querying, etc. `PhlyRestfully` chooses to err on the side of caution
and not add all query string parameters automatically to collection links. I
recommend that you whitelist these via a listener:

```php
$allowedQueryParams = array('order', 'sort');
$sharedEvents->attach('Your\Controller', 'getList.post', function ($e) use ($allowedQueryParams) {
    $request = $e->getTarget()->getRequest();
    $params  = array();
    foreach ($request->getQuery() as $key => $value) {
        if (in_array($key, $allowedQueryParams)) {
            $params[$key] = $value;
        }
    }
    if (empty($params)) {
        return;
    }

    $collection = $e->getParam('collection');
    $collection->setCollectionRouteOptions(array(
        'query' => $params,
    ));
});
```

What the above does is listen on the `getList.post` event (which occurs after a
successful list retrieval), and looks in the request object for any query
parameters that are allowed.  If any are found, it adds them to the collection's
route options -- ensuring that when URL generation occurs, those query string
parameters and values will be present.

Embedding Resources
-------------------

To follow the HAL specification properly, when you embed resources within
resources, they, too, should be rendered as HAL resources. As an example,
consider the following object:

```javascript
{
    "status": "this is my current status",
    "type": "text",
    "user": {
        "id": "matthew",
        "url": "http://mwop.net",
        "github": "weierophinney"
    },
    "id": "afcdeb0123456789afcdeb0123456789"
}
```

In the above, we have an embedded "user" object. In HAL, this, too, should
be treated as a resource.

To accomplish this, simply assign a `HalResource` value as a resource value.
As an example, consider the following pseudo-code for the above example:

```php
$status = new Status(array(
    'status' => 'this is my current status',
    'type'   => 'text',
    'user'   => new HalResource(new User(array(
        'id'     => 'matthew',
        'url'    => 'http://mwop.net',
        'github' => 'weierophinney',
    ), 'matthew', 'user')),
));
```

When this object is used within a `HalResource`, it will be rendered as an
embedded resource:

```javascript
{
    "_links": {
        "self": "http://status.dev:8080/api/status/afcdeb0123456789afcdeb0123456789"
    },
    "status": "this is my current status",
    "type": "text",
    "id": "afcdeb0123456789afcdeb0123456789",
    "_embedded": {
        "user": {
            "_links": {
                "self": "http://status.dev:8080/api/user/matthew"
            },
            "id": "matthew",
            "url": "http://mwop.net",
            "github": "weierophinney"
        },
    }
}
```

This will work in collections as well.

I recommend converting embedded resources to `HalResource` instances either
during hydration, or as part of your `Resource` listener's mapping logic.

Hydrators
---------

You can specify hydrators to use with the objects you return from your resources
by attaching them to the `HalLinks` view helper/controller plugin. This can be done
most easily via configuration, and you can specify both a map of class/hydrator
service pairs as well as a default hydrator to use as a fallback. As an example,
consider the following `config/autoload/phlyrestfully.global.php` file:

```php
return array(
    'phlyrestfully' => array(
        'renderer' => array(
            'default_hydrator' => 'Hydrator\ArraySerializable',
            'hydrators' => array(
                'My\Resources\Foo' => 'Hydrator\ObjectProperty',
                'My\Resources\Bar' => 'Hydrator\Reflection',
            ),
        ),
    ),
    'service_manager' => array(
        'invokables' => array(
            'Hydrator\ArraySerializable' => 'Zend\Stdlib\Hydrator\ArraySerializable',
            'Hydrator\ObjectProperty'    => 'Zend\Stdlib\Hydrator\ObjectProperty',
            'Hydrator\Reflection'        => 'Zend\Stdlib\Hydrator\Reflection',
        ),
    ),
);
```

The above specifies `Zend\Stdlib\Hydrator\ArraySerializable` as the default
hydrator, and maps the `ObjecProperty` hydrator to the `Foo` resource, and the
`Reflection` hydrator to the `Bar` resource. Note that you need to define
invokable services for the hydrators; otherwise, the service manager will be
unable to resolve the hydrator services, and will not map any it cannot resolve.

Specifying Alternate Identifiers For URL Assembly
-------------------------------------------------

With individual resource endpoints, the identifier used in the URI is given to
the `HalResource`, regardless of the structure of the actual resource object.
However, with collections, the identifier has to be derived from the individual
resources they compose.

If you are not using the key or property "id", you will need to provide a
listener that will derive and return the identifier. This is done by attaching
to the `getIdFromResource` event of the `PhlyRestfully\Plugin\HalLinks` class.

Let's look at an example. Consider the following resource structure:

```javascript
{
    "name": "mwop",
    "fullname": "Matthew Weier O'Phinney",
    "url": "http://mwop.net"
}
```

Now, let's consider the following listener:

```php
$listener = function ($e) {
    $resource = $e->getParam('resource');
    if (!is_array($resource)) {
        return false;
    }

    if (!array_key_exists('name', $resource)) {
        return false;
    }

    return $resource['name'];
};
```

The above listener, on encountering the resource, would return "mwop", as that's
the value of the "name" property.

There are two ways to attach to this listener. First, we can grab the `HalLinks`
plugin/helper, and attach directly to its service manager:

```php
// Assume $services is the application ServiceManager instance
$helpers = $services->get('ViewHelperManager');
$links   = $helpers->get('HalLinks');
$links->getEventManager()->attach('getIdFromResource', $listener);
```

Alternately, you can do so via the `SharedEventManager` instance:

```php
// Assume $services is the application ServiceManager instance
$sharedEvents = $services->get('SharedEventManager');

// or, if you have access to another event manager instance:
$sharedEvents = $events->getSharedManager();

// Then, connect to it:
$sharedEvents('PhlyRestfully\Plugin\HalLinks', 'getIdFromResource', $listener);
```

Controller Events
-----------------

Each of the various REST endpoint methods - `create()`, `delete()`,
`deleteList()`, `get()`, `getList()`, `patch()`, `update()`, and `replaceList()`
\- trigger both a `{methodname}.pre` and a `{methodname}.post` event. The "pre"
event is executed after validating arguments, and will receive any arguments
passed to the method; the "post" event occurs right before returning from the
method, and receives the same arguments, plus the resource or collection, if
applicable.

These methods are useful in the following scenarios:

- Specifying custom HAL links
- Aggregating additional request parameters to pass to the resource object

As an example, if you wanted to add a "describedby" HAL link to every resource
or collection returned, you could do the following:

```php
// Methods we're interested in
$methods = array(
    'create.post',
    'get.post',
    'getList.post',
    'patch.post',
    'update.post',
    'replaceList.post',
);
// Assuming $sharedEvents is a ZF2 SharedEventManager instance
$sharedEvents->attach('My\Namespaced\ResourceController', $methods, function ($e) {
    $resource = $e->getParam('resource', false);
    if (!$resource) {
        $resource = $e->getParam('collection', false);
    }

    if (!$resource instanceof \PhlyRestfully\LinkCollectionAwareInterface) {
        return;
    }

    $link = new \PhlyRestfully\Link('describedby');
    $link->setRoute('api/docs');
    $resource->getLinks()->add($link);
});
```

Using The ApiProblemListener
----------------------------

If you need to return all dispatch errors as JSON to the clients, you can easily
bind the `ApiProblemListener` to the `dispatch.error` event.

```php
public function onBootstrap(MvcEvent $mvcEvent)
{
    $eventManager = $mvcEvent->getApplication()->getEventManager();
    $eventManager->attach(
        'dispatch.error',
        function (MvcEvent $mvcEvent) {
            $application = $mvcEvent->getApplication();
            $apiProblemListener = $application->getServiceManager()->get('PhlyRestfully\ApiProblemListener');
            $application->getEventManager()->attach($apiProblemListener);
        }
    );
}
```

Example result set:

```json
{
    "describedBy": "http:\/\/www.w3.org\/Protocols\/rfc2616\/rfc2616-sec10.html",
    "title": "Not Found",
    "httpStatus": 404,
    "detail": "Page not found."
}
```

You can also bind it to specific controllers:

```php
public function onBootstrap(MvcEvent $mvcEvent)
{
    $eventManager = $mvcEvent->getApplication()->getEventManager();
    $eventManager->attach(
        'route',
        function (MvcEvent $mvcEvent) {
            $controller = $mvcEvent->getRouteMatch()->getParam('controller');
            if($controller == 'MyController') {
                $application = $mvcEvent->getApplication();
                $apiProblemListener = $application->getServiceManager()->get('PhlyRestfully\ApiProblemListener');
                $application->getEventManager()->attach($apiProblemListener);
            }
        },
        -100
    );
}
```

*Note:* you don't need to do this for the ResourceController class

Returning a Problem API Result From A Listener
----------------------------------------------

At times, you may want to intercept calls before the controller does its work,
and return a Problem API result. This can be done by setting the `api-problem`
member of the `MvcEvent` in any listener that triggers before the controller's
`dispatch` listener. As an example:

```php
$sharedEvents->attach('PhlyRestfully\ResourceController', 'dispatch', function ($e) {
    // do some work, and determine that a problem exists
    $problem = new \PhlyRestfully\ApiProblem(500, 'some error occurred!');
    $e->setParam('api-problem', $problem);
}, 100);
```

The `dispatch` listener of the controller will discover this, and prevent
execution of any action/RESTful methods.

Implementing child resources
----------------------------

Assume the following two routes:

- `api/v1/pastes[/:paste_id]` for pastebin resources
- `/api/v1/pastes[/:paste_id]/comments[/:comment_id]` for comments on individual
  pastebins

For each resource's `ResourceController`, you will need to specify the resource
identifier name:

```php
// For the "pastes" resource:
$controller->setIdentifierName('paste_id');

// For the "comments" resource:
$controller->setIdentifierName('comment_id');
```

What's left to do?

If you try and run the code you will notice that the resources associated with
comments does not have access to the `paste_id`. To do this
we must add a listener on the controller events, and use it to inject the
resource event parameters with the `paste_id` from the route matches.

In the following example, we assume the comments controller service is named
`Api\Controller\Comments`, and we attach to its RESTful events in order to
inject the `paste_id` as a resource event parameter.

```php
$sharedEvents->attach(
    'Api\Controller\Comments',
    array('create', 'delete', 'get', 'getList', 'patch', 'update'),
    function ($e) {
        $matches  = $e->getRouteMatch();
        $target   = $e->getTarget();
        $resource = $target->getResource();

        $resource->setEventParams(array(
            'paste_id' => $matches->getParam('paste_id'),
        ));
    }
);
```

And now to access the ```paste_id``` in the Resource, do the following

```php
$pasteId = $e->getParam('paste_id')
```

Providing Problem API Details Via Exceptions
--------------------------------------------

If you want to provide custom "describedBy" or "title" fields for your Problem
API results, or additional details, when throwing an exception from your
resource listener, you can do so.

Any exception that implements
`PhlyRestfully\Exception\ProblemExceptionInterface` can provide these details;
`PhlyRestfully\Exception\DomainException` already implements this, and, by
extension, the `CreationException`, `UpdateException`, and `PatchException` do
as well.

`DomainException` defines three setters to set these fields:

- `setTitle($title)`
- `setDescribedBy($uri)`
- `setAdditionalDetails(array $details)`

As an example:

```php
$ex = new CreationException('Already exists', 409);
$ex->setTitle('Matching resource already exists');
$ex->setDescribedBy('http://example.com/api/help/409');
$ex->setAdditionalDetails(array(
    'conflicting_resource' => $someUri,
));
throw $ex;
```

Upgrading
=========

If you were using version 1.0.0 or earlier (the version presented at PHP
Benelux 2013), you will need to make some changes to your application to get it
to work.

- First, the terminology has changed, as have some class names, to reference
  "resources" instead of "items"; this is more in line with RESTful terminology.
    - As such, if you had any code using `PhlyRestfully\HalItem`, it should now
      reference `PhlyRestfully\HalResource`. Similarly, in that class, you will
      access the actual resource object now from the `resource` property
      instead of the `item` property. (This should only affect those post-1.0.0).
    - If you want to create link for an individual resource, use the
      `forResource` method of `HalLinks`, and not the `forItem` method.
    - `InvalidItemException` was renamed to `InvalidResourceException`.
- A number of items were moved from the `RestfulJsonModel` to the
  `RestfulJsonRenderer`.
    - Hydrators
    - The flag for displaying exception backtraces; in fact, you can use
      the `view_manager.display_exceptions` configuration setting to set
      this behavior.
- All results from the `ResourceController` are now pushed to a `payload`
  variable in the view model.
    - Additionally, `ApiProblem`, `HalResource`, and `HalCollection` are
      first-class objects, and are used as the `payload` values.
- The `Links` plugin was renamed to `HalLinks`, and is now also available as
  a view helper.


LICENSE
=======

This module is licensed using the BSD 2-Clause License:

```
Copyright (c) 2013, Matthew Weier O'Phinney
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

- Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.
- Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```
