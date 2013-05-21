.. _ref/api-problem-listener:

The API-Problem listener
========================

In the chapter on :ref:`error reporting <phlyrestfully.error-reporting>`, I noted that
PhlyRestfully has standardized on the API-Problem format for reporting errors.

Currently, an API-Problem response will be created automatically for any of the
following conditions:

- raising a ``PhlyRestfully\Exception\CreationException`` inside a ``create``
  listener.
- raising a ``PhlyRestfully\Exception\PatchException`` inside a ``patch``
  listener.
- raising a ``PhlyRestfully\Exception\UpdateException`` inside an ``update``
  listener.
- raising an exception in any other ``PhlyRestfully\Resource`` event listener.

If the exception you raise implements
``PhlyRestfully\Exception\ProblemExceptionInterface`` -- which
``PhlyRestfully\Exception\DomainException`` does, as does its descendents, the
``CreationException``, ``PatchException``, and ``UpdateException`` -- you can
set additional details in the exception instance before throwing it, allowing
you to hit to ``PhlyRestfully\ApiProblem`` how to render itself.

There's another way to create and return an API-Problem payload, however: create
and return an instance of ``PhlyRestfully\ApiProblem`` from any of the
``Resource`` event listeners. This gives you fine-grained control over creation
of the problem object, and avoids the overhead of exceptions.

However, there's another way to receive an API-Problem result: raising an
exception. For this the listener becomes important.

The Listener
------------

``PhlyRestfully\Module`` registers a listener with the identifier
``PhlyRestfully\ResourceController`` on its ``dispatch`` event. This event then
registers the ``PhlyRestfully\ApiProblemListener`` on the application ``render``
event. Essentially, this ensures that the listener is only registered if a
controller intended as a RESTful resource endpoint is triggered.

The listener checks to see if the ``MvcEvent`` instance is marked as containing
an error. If so, it checks to see if the ``Accept`` header is looking for a JSON
response, and, finally, if so, it marshals an ``ApiProblem`` instance from the
exception, setting it as the result.

This latter bit, the ``Accept`` header matching, is configurable. If you want to
allow an API-Problem response for other than the default set of mediatypes
(``application/hal+json``, ``application/api-problem+json``, and
``application/json``), you can do so via your configuration. Set the value in
the ``accept_filter`` subkey of the ``phlyrestfully`` configuration; the value
should be a comma-separated set of mimetypes.

.. code-block:: php
    :linenos:

    return array(
        'phlyrestfully' => array(
            // ...
            'accept_filter' => 'application/json,text/json',
        ),
    );

.. index:: api-problem, error reporting, exception, accept
