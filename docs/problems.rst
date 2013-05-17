.. _phlyrestfully.error-reporting:

Error Reporting
===============

HAL does a great job of defining a generic mediatype for resources with
relational links. However, how do you go about reporting errors? HAL is silent
on the issue.

REST advocates indicate that HTTP response status codes should be used, but
little has been done to standardize on the response format.

For JSON APIs, though, two formats are starting to achieve large adoption:
``vnd.error`` and ``API-Problem``. In PhlyRestfully, I have provided support for
returning ``Api-Problem`` payloads.

API-Problem
-----------

This mediatype, ``application/api-problem+json`` is `via the IETF
<http://tools.ietf.org/html/draft-nottingham-http-problem-02>`_, and actually
also includes an XML variant. The structure includes the following properties:

- **describedBy**: a URL to a document describing the error condition (required)
- **title**: a brief title for the error condition (required)
- **httpStatus**: the HTTP status code for the current request (optional)
- **detail**: error details specific to this request (optional)
- **supportId**: a URL to the specific problem occurrence (e.g., to a log message) (optional)

As an example payload:

.. code-block:: http

    HTTP/1.1 500 Internal Error
    Content-Type: application/api-problem+json
    
    {
        "describedBy": "http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html",
        "detail": "Status failed validation",
        "httpStatus": 500,
        "title": "Internal Server Error"
    }

The specification allows a large amount of flexibility -- you can have your own
custom error types, so long as you have a description of them to link to. You
can provide as little or as much detail as you want, and even decide what
information to expose based on environment.

PhlyRestfully Choices
---------------------

The specification indicates that every error needs to include a "describedBy"
field, pointing to a URI with more information on the problem type. Often, when
you start a project, you may not want to worry about this up front -- the HTTP
status code may be enough to begin. As such, PhlyRestfully assumes that if you
do not provide a "describedBy" field, it will link to the URI describing the
HTTP status codes.
