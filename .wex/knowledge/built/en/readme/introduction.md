`symfony-remote-dila` reads the open data of the DILA (Direction de l'information légale et administrative, Licence Ouverte) from a Symfony app. It holds two sources:

- the verbatim reports of the Assemblée nationale (`OPENDATA/Debats/AN/`, October 2011 on), downloaded publication by publication and read into debate points — a title, a date, and who said what, each member of parliament with the number of their record at the Assemblée;
- the public speeches vie-publique.fr records (`OPENDATA/DISCOURS_PUBLICS/`, Licence Ouverte 2.0): some 150 000 records of statements, interviews, press conferences and releases — the President's since 1974, the Government's, the parties' and the unions' —, each with who spoke and as what, when, its subjects, and the page where its text is read. The records only: the DILA does not publish the texts.

`DilaAnDebatesClient` and `DilaPublicSpeechesClient` are `symfony-remote`s, so `remote:status` checks them. It knows nothing of the app's entities: what to make of the points is the app's.

A download is tried again a few times, then raised as php-remote's `RemoteException`, as is an index or a report the package can no longer read — the DILA changed its format. Run the calls through `RemoteGuard` where the app must go on without them.

The speeches' licence asks a reuse to credit the DILA, with the file's address and date.
