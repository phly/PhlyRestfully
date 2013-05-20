.. _phlyrestfully.options:

Whitelisting HTTP Methods
=========================

If your API is to adhere to the Richardson Maturity Model's level 2 or higher,
you will be using HTTP verbs to interact with it: ``GET``, ``POST``, ``PUT``,
``DELETE``, and ``PATCH`` being the most common. However, based on the resource
and whether or not the end point is a collection, you may want to allow
different HTTP methods. How can you do that? and how do you enforce it?

HTTP provides functionality around this topic via another HTTP method,
``OPTIONS``, and a related HTTP response header, ``Allow``. 

Calls to ``OPTIONS`` are non-cacheable, and may provide a response body if
desired. They _should_ emit an ``Allow`` header, however, detailing which HTTP
request methods are allowed on the current URI.

Consider the following request:

.. code-block:: http

    OPTIONS /api/user
    Host: example.org

with its response:

.. code-block:: http

    HTTP/1.1 200 OK
    Allow: GET, POST

This tells us that for the URI ``/api/user``, you may emit either a ``GET`` or
``POST`` request.

What happens if a malicious user tries something else? You should respond with a
"405 Not Allowed" status, and indicate what _is_ allowed:

.. code-block:: http

    HTTP/1.1 405 Not Allowed
    Allow: GET, POST

PhlyRestfully bakes this into its ``PhlyRestfully\ResourceController``
implementation, allowing you to specify via configuration which methods are
allowed both for collections and individual resources handled by the controller.

.. index:: options, http, allow
