TODO
====

Tasks:

- [X] CS - docblocks (file, class, methods)
- [X] Allow setting resource route params/options in metadata map? (Not
  necessary; if the resource is already in the metadata map, the resource
  route, params, and options are already defined!)
- [X] Changes to HalLinks factory to allow injecting metadata map
- [X] Inject MetadataMap into ResourceController, and use it to create
  HalResource/HalCollection items if the class returned from the resource is in
  the map. (Would not even need to inject the MetadataMap, as it's available in
  the HalLinks plugin already...)
- [X] Documentation
