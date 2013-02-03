TODO
====

- Create ApiProblem object
- Create HalItem and HalCollection objects
- Modify ResourceController to return objects of above types
  - Compose hydrator, and pass into above objects and/or view model?
  - use special keys in view model for collections, items, and api-problem
  - Remove controller plugins for generating api problem results, links
- Additional changes to ResourceController
  - Pass route name info to view model?
  - have property that indicates response headers that should always be present,
    and inject these into the view model?
  - Move "isMethodAllowedForItem()" and "isMethodAllowedForResource()" checks,
    and "createMethodNotAllowedForItem()" and "createMethodNotAllowedResponse()"
    functionality into listeners?
    - Maybe a new event triggered at the beginning of onDispatch()
- Create RestfulJsonRenderer to check for above types of objects when rendering
  - Render ApiProblem objects
  - Render HalItem and HalCollection objects
    - Use helper system to produce links for items and collections (based on
      pagination)
      - Create view helper for rendering links
      - Create view helper for producing links from paginated collection
      - Create view helper for producing links from non-paginated collection
- Update RestfulJsonStrategy to produce content-type header based on ApiProblem
  or HalItem/Collection
- Update RestfulJsonStrategy to allow looping through key of view model in order
  to set additional headers
- Potentially add Link header, duplicating HAL, for purposes of API
  discoverability?
