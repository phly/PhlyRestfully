.. ZF2 module for implementing RESTful JSON APIs in Hypermedia Application
   Language (HAL)

Welcome to PhlyRestfully!
=========================

PhlyRestfully is a `Zend Framework 2 <http://framework.zend.com>`_ module for
implementing RESTful JSON APIs in `Hypermedia Application Language (HAL)
<http://stateless.co/hal_specification.html>`_. It provides a workflow for
mapping persistence to resources to expose via your API.

For error reporting, it uses `API-Problem <http://tools.ietf.org/html/draft-nottingham-http-problem-02>`_.

Contents:

.. toctree::
   :hidden:
   
   halprimer
   problems
   options
   basics/index
   basics/resources
   basics/controllers
   basics/example
   ref/resource-event
   ref/controller-events
   ref/advanced-routing
   ref/hydrators
   ref/collections-and-pagination
   ref/embedding-resources
   ref/metadata-map
   ref/api-problem-listener
   ref/alternate-resource-return-values
   ref/child-resources

RESTful JSON API Primer
-----------------------

    * :doc:`halprimer`
    * :doc:`problems`
    * :doc:`options`

PhlyRestfully Walkthrough
-------------------------

    * :doc:`basics/index`
    * :doc:`basics/resources`
    * :doc:`basics/controllers`
    * :doc:`basics/example`

Reference Guide
---------------

    * :doc:`ref/resource-event`
      In which I talk about the methods available on the resource event, and how
      to whitelist query parameters.
    * :doc:`ref/controller-events`
      In which I detail all the controller events, and how and why you would tie
      into them.
    * :doc:`ref/advanced-routing`
      In which I talk about the renderCollection.resource event, how to specify
      alternate identifiers for resources and collections, etc.
    * :doc:`ref/hydrators`
    * :doc:`ref/collections-and-pagination`
      In which I talk both about returning paginators in order to have paginated
      collections, as well as how to pass query and route parameters to
      resources.
    * :doc:`ref/embedding-resources`
      In which I talk about embedded resources/collections.
    * :doc:`ref/metadata-map`
      In which I talk about the metadata map, and how it simplifies much of the
      above.
    * :doc:`ref/api-problem-listener`
    * :doc:`ref/alternate-resource-return-values`
      In which I discuss returning HalResource, HalCollection, and ApiProblem
      results from resource listeners in order to have full control over them.
      Additionally, will show using custom exceptions in order to shape
      ApiProblem results.
    * :doc:`ref/child-resources`
      This can be heavily modified from the current README to instead show using
      the metadata map.

Api docs:
---------

`API docs are available. <_static/phpdoc/index.html>`_


Indices and tables
==================

* :doc:`index`
* :ref:`genindex`
* :ref:`search`

