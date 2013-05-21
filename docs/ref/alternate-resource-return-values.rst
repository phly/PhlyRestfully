.. _ref/alternate-resource-return-values:

Alternate resource return values
================================

Typically, you should return first-class objects or arrays from your
``Resource`` listeners, and use the :ref:`hydrators map <ref/hydrators>` and
:ref:`metadata map <ref/metadata-map>` for hinting to PhlyRestfully how to
render the various resources.

However, if you need to heavily optimize for performance, or want to customize
your resource or collection instances without needing to wire more event
listeners, you have another option: return ``HalResource``, ``HalCollection``,
or ``ApiProblem`` instances directly from your ``Resource`` listeners, or the
objects they delegate to.

HalResource and HalCollection
-----------------------------

``PhlyRestfully\HalResource`` and ``PhlyRestfully\HalCollection`` are simply
wrappers for the resources and collections you create, and provide the ability
to aggregate referential links. Links are aggregated in a
``PhlyRestfully\LinkCollection`` as individual ``PhlyRestfully\Link`` objects. 

``HalResource`` requires that you pass a resource and its identifier in the
constructor, and then allows you to aggregate links:

.. code-block:: php
    :linenos:

    use PhlyRestfully\HalResource;
    use PhlyRestfully\Link;

    // Create the HAL resource
    // Assume $user is an object representing a user we want to
    // render; we could have also used an associative array.
    $halResource = new HalResource($user, $user->getId());

    // Create some links
    $selfLink = new Link('self');
    $selfLink->setRoute('user', array('user_id' => $user->getId()));

    $docsLink = new Link('describedBy');
    $docsLink->setRoute('api/help', array('resource' => 'user'));

    $links = $halResource->getLinks();
    $links->add($selfLink)
         ->add($docsLink);

The above example creates a ``HalResource`` instance based on something we
plucked from our persistence layer. We then add a couple of links describing
"self" and "describedBy" relations, pointing them to specific routes and using
specific criteria.

We can do the same for collections. With a collection, we need to specify the
object or array representing the collection, and then provide metadata for
various properties, such as:

- The route to use for generating links for the collection, including any extra
  routing parameters or options.
- The route to use for generating links for the resources in the collection,
  including any extra routing parameters or options.
- The name of the identifier key within the embedded resources.
- Additional attributes/properties to render as part of the collection. These
  would be first-class properties, and not embedded resources.
- The name of the embedded collection (which defaults to "items").

The following example demonstrates each of these options, as well as the
addition of several relational links.

.. code-block:: php
    :linenos:

    use PhlyRestfully\HalCollection; 
    use PhlyRestfully\Link; 

    // Assume $users is an iterable set of users for seeding the collection.
    $collection = new HalCollection($users);

    $collection->setCollectionRoute('api/user');
    // Assume that we need to specify a version within the URL:
    $collection->setCollectionRouteParams(array(
        'version' => 2,
    ));
    // Tell the router to allow query parameters when generating the URI:
    $collection->setCollectionRouteOptions(array(
        'query' => true,
    ));

    // Set the resource route, params, and options
    $collection->setResourceRoute('api/user');
    $collection->setResourceRouteParams(array(
        'version' => 2,
    ));
    $collection->setResourceRouteOptions(array(
        'query' => null, // disable query string params
    ));

    // Set the collection name:
    $collection->setCollectionName('users');

    // Set some attributes: current page, total number of pages, total items:
    $collection->setAttributes(array(
        'page'        => $page, // assume we have this from somewhere else
        'pages_count' => count($users),
        'users_count' => $users->countAllItems(),
    ));

    // Add some links
    $selfLink = new Link('self');
    $selfLink->setRoute('api/user', array(), array('query' => true));
    $docsLink = new Link('describedBy');
    $docsLink->setRoute('api/help', array('resource' => 'users'));

    $links = $collection->getLinks();
    $links->add($selfLink)
        ->add($docsLink);

Using this approach, you can fully customize the ``HalResource`` and
``HalCollection`` objects, allowing you to set custom links, customize many
aspects of output, and more. You could even extend these classes to provide
additional behavior, and provide your own ``HalLinks`` implementation that
renders them differently if desired.

The downside, however, is that it ties your implementation directly to the
PhlyRestfully implementation, which may limit some use cases.

ApiProblem
----------

Just as you can return a ``HalResource`` or ``HalCollection``, you can also
directly return a ``PhlyRestfully\ApiProblem`` instance if desired, allowing you
to fully craft the return value.

Unlike ``HalResource`` and ``HalCollection``, however, ``ApiProblem`` does not
allow you to set most properties after instantiation, which means you'll need to
ensure you have all your details up front.

The signature of the constructor is:

.. code-block:: php
    :linenos:

    public function __construct(
        $httpStatus,                // HTTP status code used for the response
        $detail,                    // Summary of what happened
        $describedBy = null,        // URI to a description of the problem
        $title = null,              // Generic title for the problem
        array $additional = array() // Additional properties to include in the payload
    );

Essentially, you simply instantiate and return an ``ApiProblem`` from your
listener, and it will be used directly.

.. code-block:: php
    :linenos:

    use PhlyRestfully\ApiProblem;
    
    return new ApiProblem(
        418,
        'Exceeded rate limit',
        $urlHelper('api/help', array('resource', 'error_418')),
        "I'm a teapot",
        array(
            'user'  => $user,
            'limit' => '60/hour',
        )
    );

And with that, you have a fully customized error response.

.. index:: api-problem, hal, resource, collection, HalLinks
