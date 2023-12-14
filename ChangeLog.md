WebSockets change log
=====================

## ?.?.? / ????-??-??

## 3.0.0 / ????-??-??

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