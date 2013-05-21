PhlyRestfully: ZF2 Module for JSON REST Services
================================================

This module provides structure and code for quickly implementing RESTful APIs
that use JSON as a transport.

It allows you to create RESTful JSON APIs that use the following standards:

- [HAL](http://tools.ietf.org/html/draft-kelly-json-hal-03), used for creating
  hypermedia links
- [Problem API](http://tools.ietf.org/html/draft-nottingham-http-problem-02),
  used for reporting API problems

[Documentation is available at rtfd.org](https://phlyrestfully.readthedocs.org/en/latest/).

Upgrading
=========

If you were using version 1.0.0 or earlier (the version presented at PHP
Benelux 2013), you will need to make some changes to your application to get it
to work.

- First, the terminology has changed, as have some class names, to reference
  "resources" instead of "items"; this is more in line with RESTful terminology.
    - As such, if you had any code using `PhlyRestfully\HalItem`, it should now
      reference `PhlyRestfully\HalResource`. Similarly, in that class, you will
      access the actual resource object now from the `resource` property
      instead of the `item` property. (This should only affect those post-1.0.0).
    - If you want to create link for an individual resource, use the
      `forResource` method of `HalLinks`, and not the `forItem` method.
    - `InvalidItemException` was renamed to `InvalidResourceException`.
- A number of items were moved from the `RestfulJsonModel` to the
  `RestfulJsonRenderer`.
    - Hydrators
    - The flag for displaying exception backtraces; in fact, you can use
      the `view_manager.display_exceptions` configuration setting to set
      this behavior.
- All results from the `ResourceController` are now pushed to a `payload`
  variable in the view model.
    - Additionally, `ApiProblem`, `HalResource`, and `HalCollection` are
      first-class objects, and are used as the `payload` values.
- The `Links` plugin was renamed to `HalLinks`, and is now also available as
  a view helper.


LICENSE
=======

This module is licensed using the BSD 2-Clause License:

```
Copyright (c) 2013, Matthew Weier O'Phinney
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

- Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.
- Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```
