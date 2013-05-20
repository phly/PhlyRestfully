.. _phlyrestfully.hal:

HAL Primer
==========

HAL, short for "Hypermedia Application Language", is an `open specification
describing a generic structure for RESTful resources
<http://stateless.co/hal_specification.html>`_. The structure it proposes
readily achieves the `Richardson Maturity Model
<http://martinfowler.com/articles/richardsonMaturityModel.html>`_'s Level 3 by
ensuring that each resource contains relational links, and that a standard,
identifiable structure exists for embedding other resources.

Essentially, good RESTful APIs should:

- expose resources
- via HTTP, using HTTP verbs to manipulate them
- and provide canonical links to themselves, as well as link to other, related
  resources.

.. _phlyrestfully.hal.hypermedia:

Hypermedia Type
---------------

HAL presents two hypermedia types, one for XML and one for JSON. Typically, the
type is only relevant for resources returned by the API, as relational links are
not usually submitted when creating, updating, or deleting resources.

The generic mediatype that HAL defines for JSON APIs is "application/hal+json".

.. _phlyrestfully.hal.resources:

Resources
---------

For JSON resources, the minimum you must do is provide a "_links" property
containing a "self" relational link. As an example:

.. code-block:: javascript

    {
        "_links": {
            "self": {
                "href": "http://example.org/api/user/matthew"
            }
        }
        "id": "matthew",
        "name": "Matthew Weier O'Phinney"
    }

If you are including other resources embedded in the resource you are
representing, you will provide an "_embedded" property, containing the named
resources. Each resource will be structured as a HAL resource, and contain at
least a "_links" property with a "self" relational link.

.. code-block:: javascript

    {
        "_links": {
            "self": {
                "href": "http://example.org/api/user/matthew"
            }
        }
        "id": "matthew",
        "name": "Matthew Weier O'Phinney",
        "_embedded": {
            "contacts": [
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/mac_nibblet"
                        }
                    },
                    "id": "mac_nibblet",
                    "name": "Antoine Hedgecock"
                },
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/spiffyjr"
                        }
                    },
                    "id": "spiffyjr",
                    "name": "Kyle Spraggs"
                }
            ],
            "website": {
                "_links": {
                    "self": {
                        "href": "http://example.org/api/locations/mwop"
                    }
                },
                "id": "mwop",
                "url": "http://www.mwop.net"
            },
        }
    }

Note that each item in the "_embedded" list can be either a resource or an array
of resources. That takes us to the next topic: collections.

.. _phlyrestfully.hal.collections:

Collections
-----------

Collections in HAL are literally just arrays of embedded resources. A typical
collection will include a "self" relational link, but also pagination links -
"first", "last", "next", and "prev" are standard relations. Often APIs will also
indicate the total number of resources, how many are delivered in the current
payload, and potentially other metadata about the collection.

.. code-block:: javascript

    {
        "_links": {
            "self": {
                "href": "http://example.org/api/user?page=3"
            },
            "first": {
                "href": "http://example.org/api/user"
            },
            "prev": {
                "href": "http://example.org/api/user?page=2"
            },
            "next": {
                "href": "http://example.org/api/user?page=4"
            },
            "last": {
                "href": "http://example.org/api/user?page=133"
            }
        }
        "count": 3,
        "total": 498,
        "_embedded": {
            "users": [
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/mwop"
                        }
                    },
                    "id": "mwop",
                    "name": "Matthew Weier O'Phinney"
                },
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/mac_nibblet"
                        }
                    },
                    "id": "mac_nibblet",
                    "name": "Antoine Hedgecock"
                },
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/spiffyjr"
                        }
                    },
                    "id": "spiffyjr",
                    "name": "Kyle Spraggs"
                }
            ]
        }
    }

The various relational links for the collection make it trivial to traverse the
API to get a full list of resources in the collection. You can easily determine
what page you are on, and what the next page should be (and if you are on the
last page).

Each item in the collection is a resource, and contains a link to itself, so you
can get the full resource, but also know its canonical location. Often, you may
not embed the full resource in a collection -- just the bits that are relevant
when doing a quick list. As such, having the link to the individual resource
allows you to get the full details later if desired.

.. _phlyrestfully.hal.interactions:

Interacting with HAL
--------------------

Interacting with HAL is usually quite straight-forward:

- Make a request, using the Accept header with a value of ``application/json`` or
  ``application/hal+json`` (the latter really isn't necessary, though).

- If ``POST``ing, ``PUT``ting, ``PATCH``ing, or ``DELETE``ing a resource, you
  will usually use a Content-Type header of either ``application/json``, or some
  vendor-specific mediatype you define for your API; this mediatype would be
  used to describe the particular structure of your resources _without_ any HAL
  "_links". Any "_embedded" resources will typically be described as properties
  of the resource, and point to the mediatype relevant to the embedded resource.

- The API will respond with a mediatype of ``application/hal+json``.

When creating or updating a resource (or collection), you will submit the
object, without relational links; the API is responsible for assigning the
links. If we consider the embedded resources example from above, I would create
it like this:

.. code-block:: http

    POST /api/user
    Accept: application/json
    Content-Type: application/vnd.example.user+json

    {
        "id": "matthew",
        "name": "Matthew Weier O'Phinney",
        "contacts": [
            {
                "id": "mac_nibblet",
            },
            {
                "id": "spiffyjr",
            }
        ],
        "website": {
            "id": "mwop",
        }
    }

The response would look like this:

.. code-block:: http

    HTTP/1.1 201 Created
    Content-Type: application/hal+json
    Location: http://example.org/api/user/matthew

    {
        "_links": {
            "self": {
                "href": "http://example.org/api/user/matthew"
            }
        }
        "id": "matthew",
        "name": "Matthew Weier O'Phinney",
        "_embedded": {
            "contacts": [
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/mac_nibblet"
                        }
                    },
                    "id": "mac_nibblet",
                    "name": "Antoine Hedgecock"
                },
                {
                    "_links": {
                        "self": {
                            "href": "http://example.org/api/user/spiffyjr"
                        }
                    },
                    "id": "spiffyjr",
                    "name": "Kyle Spraggs"
                }
            ],
            "website": {
                "_links": {
                    "self": {
                        "href": "http://example.org/api/locations/mwop"
                    }
                },
                "id": "mwop",
                "url": "http://www.mwop.net"
            },
        }
    }

``PUT`` and ``PATCH`` operate similarly.

.. index:: hypermedia application language, hal, mediatype, resource, collection
