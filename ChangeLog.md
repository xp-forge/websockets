WebSockets change log
=====================

## ?.?.? / ????-??-??

## 4.1.0 / 2025-01-12

* Added support for `xp-forge/uri` version 3.0+ - @thekid
* **Heads up**: Deprecated passing origin to `WebSocket` constructor. It
  should be passed inside the headers when calling *connect()*.
  (@thekid)
* Merged PR #7: Added ability to pass path and query string to `WebSocket`
  constructor
  (@thekid)
* Fixed "Call to a member function message() on null" errors when using
  an already connected socket in the `WebSocket` constructor.
  (@thekid)

## 4.0.0 / 2024-10-05

* Dropped support for PHP < 7.4, see xp-framework/rfc#343 - @thekid
* **Heads up:** The `websocket.Listener`'s *close()* method now has two
  additional parameters, *code* and *reason*, which need to be added to
  implementations.
  (@thekid)
* Merged PR #6: Add WebSocket client implementation - @thekid

## 3.1.0 / 2024-03-24

* Made compatible with XP 12 - @thekid

## 3.0.0 / 2023-12-14

* Made it possible to directly return a listener from `Listeners::serve()`.
  (@thekid)
* Merged PR #5: Make it possible to react to connections being opened and
  closed. Implement the `websocket.Listener` class' methods *open()* and
  *close()* respectively. These are NOOPs by default.
  (@thekid)

## 2.1.0 / 2023-12-10

* Merged PR #4: Add accessor for system's temporary directory - @thekid
* Merged PR #3: Access environment variable accessors - @thekid
* Fixed `xp ws` command to work correctly via Composer - @thekid
* Added PHP 8.4 to test matrix - @thekid
* Merged PR #2: Migrate to new testing library - @thekid

## 2.0.0 / 2021-10-24

* Made library compatible with XP 11 - @thekid
* Implemented xp-framework/rfc#341, dropping compatibility with XP 9
  (@thekid)

## 1.0.1 / 2020-04-10

* Made compatible with `xp-forge/uri` version 2.0.0 - @thekid

## 1.0.0 / 2019-12-01

* Implemented xp-framework/rfc#334: Drop PHP 5.6. The minimum required
  PHP version is now 7.0.0!
  (@thekid)
* Made compatible with XP 10 - @thekid

## 0.2.0 / 2019-09-29

* Fixed *Skipped installation of bin bin/xp.xp-forge.web* error
  when installing with Composer
  (@thekid)

## 0.1.0 / 2019-09-23

* Hello World! First release - @thekid