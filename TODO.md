TODO
====

Notes:

Tasks:

- [ ] Embedded items
  Technically, when doing collections, you should have a key, "\_embedded", which
  is a hash. When doing a collection, you'll have a key that indicates the "type"
  of resources under it -- for example: 

```javascript
  {"\_links": { ... }, "\_embedded": { "books": [ ... ] } }
```

  This will mean that the controller will need to inject the collection name 
  into the view model, and the renderer will need to use that name when 
  rendering the collection.

  Additionally, we may want to allow recursive rendering of individual items,
  looking for other RestfulJsonModel child items; these would be embedded in
  the representation using the captureTo value as the "type", and the object
  itself would act as a resource with links.

  This will address issue #1 (I believe).
- [X] Additional changes to ResourceController
  - [ ] have property that indicates response headers that should always be present,
    and inject these into the view model?
  - [ ] Move "isMethodAllowedForItem()" and "isMethodAllowedForResource()" checks,
    and "createMethodNotAllowedForItem()" and "createMethodNotAllowedResponse()"
    functionality into listeners?
    - [ ] Maybe a new event triggered at the beginning of onDispatch()
- [ ] Update RestfulJsonStrategy to allow looping through key of view model in order
  to set additional headers
- [ ] Potentially add Link header, duplicating HAL, for purposes of API
  discoverability?

Architecture Notes
------------------

Specialized Renderer
^^^^^^^^^^^^^^^^^^^^

This would check for a special type of view model, and, if detected, process it
differently; otherwise, it hands off rendering to the default `JsonRenderer`
implementation.

In the case of a `HalItem` payload, it would generate the `_links` content, use
the hydrator to get an array version of the item, and mix in the item content.
This would allow for the more common "object + links" format.

In the case of a `HalCollection` payload, it would generate the `_links` content
for the collection, and then iterate over the collection to produce the 
individual items of the collection, generating each in the same way as a `HalItem`.

In the case of an `ApiProblem` payload, it would simply call `toArray()` on the
object, and use that as the serializable result.

This approach would both require and allow the following:

- [X] Attaching one or more hydrators to use based on item class
- [ ] Attaching one or more callbacks to use for determining item identifiers based
  on item class.
- [ ] Injecting additional collection and/or item route parameters based on
  collection and/or item class.

Specialized Strategy
^^^^^^^^^^^^^^^^^^^^

The `RestfulJsonStrategy` would obviously compose the above renderer.
Additionally, it would look for `ApiProblem` payloads, and, if detected, use
the `httpStatus` it composes to set the response status code, as well as the
content type. Additionally, if a `HalItem` or `HalCollection` payload is
detected, the appropriate content type will be provided.

Furthermore, since we'll have the generated payload, we could potentially 
create a `Link` header with pagination links, if desired; this would allow
machine traversal of links without polling full results, and thus saving
bandwidth.

Link Creation
^^^^^^^^^^^^^

Since link creation is now part of the renderer, I can compose in the view
helper manager. This allows me to use `serverUrl()` and `url()`, but also
allows creating view plugins for generating:

- [X] Individual item links
- [X] Pagination links
