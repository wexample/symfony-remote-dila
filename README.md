# symfony-remote-dila

Version: 2.0.1

The DILA's open data, as symfony-remotes: the verbatim reports of the Assemblée nationale, read into debate points

## Table of Contents

- [Installation](#installation)
- [Introduction](#introduction)

## Installation

```php
// config/bundles.php
Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle::class => ['all' => true],
Wexample\SymfonyRemoteDila\WexampleSymfonyRemoteDilaBundle::class => ['all' => true],
```

Nothing to configure. Publications are kept unpacked under `var/remote/dila` (`cache_dir`): out of the kernel's cache, and worth a volume in production, since the archives weigh about 2 GB. GNU `tar` must be on the path: the older archives are compressed with `compress`, which PHP does not read.

`symfony-remote-dila` reads the open data of the DILA (Direction de l'information légale et administrative, Licence Ouverte) from a Symfony app. It holds one source so far: the verbatim reports of the Assemblée nationale (`OPENDATA/Debats/AN/`, October 2011 on), downloaded publication by publication and read into debate points — a title, a date, and who said what, each member of parliament with the number of their record at the Assemblée.

`DilaAnDebatesClient` is a `symfony-remote`, so `remote:status` checks the index. It knows nothing of the app's entities: what to make of the points is the app's.

A download is tried again a few times, then raised as php-remote's `RemoteException`, as is an index or a report the package can no longer read — the DILA changed its format. Run the calls through `RemoteGuard` where the app must go on without them.
