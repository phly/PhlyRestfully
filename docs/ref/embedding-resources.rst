.. _ref/embedding-resources:

Embedding Resources
===================

At times, you may want to embed resources inside other resources. As an example,
consider a "user" resource: it may need to embed several addresses, multiple
phone numbers, etc.:

:ref:`HAL <phlyrestfully.hal-primer>` dictates the structure for the
representation:

.. code-block:: json

    {
        "_links": {
            "self": {
                "href": "http://example.org/api/user/mwop"
            }
        },
        "id": "mwop",
        "full_name": "Matthew Weier O\'Phinney",
        "_embedded": {
            "url": {
                "_links": {
                    "self": "http://example.org/api/user/mwop/url/mwop_net"
                },
                "url_id": "mwop_net",
                "url": "http://www.mwop.net/"
            },
            "phones": [
                {
                    "_links": {
                        "self": "http://example.org/api/user/mwop/phones/1"
                    },
                    "phone_id": "mwop_1",
                    "type": "mobile",
                    "number": "800-555-1212"
                }
            ]
        }
    }

However, what if our objects look like this:

.. code-block:: php
    :linenos:

    class User
    {
        public $id;
        public $full_name;

        /**
         * @var Url
         \*/
        public $url;

        /**
         * @var Phone[]
         \*/
        public $phones;
    }

    class Url
    {
        public $url_id;
        public $url;
    }

    class Phone
    {
        public $phone_id;
        public $type;
        public $number;
    }

How, exactly, do we ensure that the ``$url`` and ``$phones`` properties
are rendered as embedded resources?

The explicit way to handle it is within your listeners: assign the value of
these properties to a ``HalResource`` or ``HalCollection`` (depending on whether
they are single resources or collections of resources, respectively).

.. code-block:: php
    :linenos:

    $user = $persistence->fetch($id);
    $user->addresses = new HalResource($user->url, $user->url->url_id);
    $user->phones    = new HalCollection($user->phones, 'api/user/phone');

From here, you can use the techniques covered in the :ref:`advanced routing
<ref/advanced-routing>`, :ref:`advanced rendering <ref/advanced-rendering>`, and
:ref:`hydrators <ref/hydrators>` sections to ensure that the various relational
links are rendered correctly, and that the resources are properly rendered.

This is fairly straight-forward, but ultimately inflexible and prone to error.
Many times, the properties will not be public, and in many circumstances, the
setters will require specific, typed objects. As such, making a change like this
will not work.

You can work around it by creating either a proxy resource object, or converting
the resource to an array. However, there's a better way: :ref:`metadata maps
<ref/metadata-map>`.

.. index:: resource, hal, collection, metadata
