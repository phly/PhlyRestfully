# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 2.3.1 - 2016-05-31

Final release. Please use [Apigility](https://apigility.org) instead.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#136](https://github.com/phly/PhlyRestfully/pull/136) fixes autoloading and
  configuration paths in the `Module` class, due to having moved into the source
  tree.

## 2.3.0 - 2016-05-26

Final release. Please use [Apigility](https://apigility.org) instead.

### Added

- [#135](https://github.com/phly/PhlyRestfully/pull/135) adds an optional
  `$data = []` parameter to `ResourceController::deleteList()` for consistency
  with other methods.
- [#135](https://github.com/phly/PhlyRestfully/pull/135) adds support for PHP 7
  and HHVM.
- [#107](https://github.com/phly/PhlyRestfully/pull/107) suggests using
  zfr/zfr-cors to provide CORS support for your API.
- [#126](https://github.com/phly/PhlyRestfully/pull/126) adds an `__isset()`
  method to `HalResource`, ensuring you can test for the identifier and/or
  resource (e.g., via `isset($halResource->id)`).

### Deprecated

- Nothing.

### Removed

- [#135](https://github.com/phly/PhlyRestfully/pull/135) removes support for PHP
  versions less than 5.5.

### Fixed

- [#135](https://github.com/phly/PhlyRestfully/pull/135) updates all references
  to `Zend\Stdlib\Hydrator` to instead use `Zend\Hydrator`.
- [#135](https://github.com/phly/PhlyRestfully/pull/135) updates all
  `trigger()` and `triggerUntil()` usage with forwards-compatible variants.
- [#97](https://github.com/phly/PhlyRestfully/pull/97) fixes the identifier
  comparison in `ResourceController::getIdentifier()` to use a strict comparison
  to boolean false, and thus allow identifiers of `0`.
- [#104](https://github.com/phly/PhlyRestfully/pull/104) corrects the logic in
  `ResourceParametersListener::detachShared` to pass the identifier.
- [#108](https://github.com/phly/PhlyRestfully/pull/108) fixes identifier
  detection in `HalLinks::createResourceFromMetadata()`, ensuring that if no
  identifier name is present in the metadata, a null identifier is used.
- [#124](https://github.com/phly/PhlyRestfully/pull/124) fixes how HalLinks
  retrieves the identifier from an object when it is not in an `id` field,
  allowing for custom identifiers per entity.
