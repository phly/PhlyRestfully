.. _ref/collections-and-pagination:

Collections and Pagination
==========================

In most use cases, you'll not want to return a collection containing every
resource in that collection; this will quickly get untenable as the number of
resources in that collection grows. This means you'll want to paginate your
collections somehow, returning a limited set of resources at a time, delimited
by some offset in the URI (usually via query string).

Additionally, to follow the Richardson Maturity Model properly, you will likely
want to include relational links indicating the next and previous pages (if
any), and likely the first and last as well (so that those traversing the
collection know when to stop).

This gets tedious very quickly.

Fortunately, PhlyRestfully can automate the process for you, assuming you are
willing to use ``Zend\Paginator`` to help do some of the heavy lifting.

Paginators
----------

`Zend\Paginator <http://framework.zend.com/manual/2.2/en/modules/zend.paginator.introduction.html>`_
is a general purpose component for paginating collections of data. It requires
only that you specify the number of items per page of data, and the current
page.

The integration within PhlyRestfully for ``Zend\Paginator`` uses a "page" query
string variable to indicate the current page. You set the page size during
configuration:

.. code-block:: php
    :linenos:

    return array(
        'phlyrestfully' => array(
            'resources' => array(
                'Paste\ApiController' => array(
                    // ...
                    'page_size' => 10, // items per page of data
                    // ...
                ),
            ),
        ),
    );

All you need to do, then, is return a ``Zend\Paginator\Paginator`` instance from
your resource listener (or an extension of that class), and PhlyRestfully will
then generate appropriate relational links.

For example, if we consider the :ref:`walkthrough example <basics.example>`, if
our ``onFetchAll()`` method were to return a ``Paginator`` instance, the
collection included 3000 records, we'd set the page size to 10, and the request
indicated page 17, our response would include the following links:

.. code-block:: javascript

    {
        "_links": {
            "self": {
                "href": "http://example.org/api/paste?page=17
            },
            "prev": {
                "href": "http://example.org/api/paste?page=16
            },
            "next": {
                "href": "http://example.org/api/paste?page=18
            },
            "first": {
                "href": "http://example.org/api/paste
            },
            "last": {
                "href": "http://example.org/api/paste?page=300
            }
        },
        // ...
    }

Again, this functionality is built-in to PhlyRestfully; all you need to do is
return a ``Paginator`` instance, and set the ``page_size`` configuration for
your resource controller.

Manual collection links
-----------------------

If you do not want to use a ``Paginator`` for whatever reason, you can always
listen on one of the controller events that returns a collection, and manipulate
the returned ``HalCollection`` from there. The events of interest are:

- **getList.post**
- **replaceList.post**

In each case, you can retrieve the ``HalCollection`` instance via the
``collection`` parameter:

.. code-block:: php

    $collection = $e->getParam('collection');

From there, you will need to retrieve the collection's ``LinkCollection``, via
the ``getLinks()`` method, and manually inject ``Link`` instances. The following
creates a "prev" relational link based on some calculated offset.

.. code-block:: php

    $sharedEvents->attach('Paste\ApiController', 'getLinks.post', function ($e) {
        $collection = $e->getParam('collection');

        // ... calculate $someOffset ...

        $links = $collection->getLinks();
        $prev  = new \PhlyRestfully\Link('prev');
        $prev->setRoute(
            'paste/api',
            array(),
            array('query' => array('offset' => $someOffset))
        );
        $links->add($prev);
    });

This method could be extrapolated to add additional route parameters or options
as well.

With these events, you have the ability to customize as needed. In most cases,
however, if you can use paginators, do.

Query parameter white listing
-----------------------------

Often when dealing with collections, you will use query string parameters to
allow such actions as sorting, filtering, and grouping. However, by default,
those query string parameters will not be used when generating links. This is by
design, as the relational links in your resources typically should not change
based on query string parameters.

However, if you want to retain them, you can.

As noted a number of times, the ``ResourceController`` exposes a number of
events, and you can tie into those events in order to alter behavior. One method
that the ``HalCollection`` class exposes is ``setCollectionRouteOptions()``,
which allows you to set, among other things, query string parameters to use
during URL generation. As an example, consider this listener:

.. code-block:: php
    :linenos:

    $allowedQueryParams = array('order', 'sort');
    $sharedEvents->attach('Paste\ApiController', 'getList.post', function ($e) use ($allowedQueryParams) {
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

The above is a very common pattern; so common, in fact, that we've automated it.
You can whitelist query string parameters to use in URL generation for
collections using the ``collection_query_whitelist`` configuration parameter for
your resource controller:

.. code-block:: php
    :linenos:

    return array(
        'phlyrestfully' => array(
            'resources' => array(
                'Paste\ApiController' => array(
                    // ... 
                    'collection_query_whitelist' => array('order', 'sort'),
                    // ... 
                ),
            ),
        ),
    );


.. index:: paginator, event, resource controller, link, hal, query, whitelist, collection, route
