## Installation

```php
// config/bundles.php
Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle::class => ['all' => true],
Wexample\SymfonyRemoteDila\WexampleSymfonyRemoteDilaBundle::class => ['all' => true],
```

Nothing to configure. Publications are kept unpacked under `var/remote/dila` (`cache_dir`): out of the kernel's cache, and worth a volume in production, since the archives weigh about 2 GB. GNU `tar` must be on the path: the older archives are compressed with `compress`, which PHP does not read.
