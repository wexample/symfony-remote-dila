## The Assemblée's debates

```php
public function __construct(private DilaAnDebatesClient $an, private RemoteGuard $guard) {}

foreach ($this->an->listYears() as $year) {
    foreach ($this->an->listPublications($year) as $publication) {   // AN_2024123.taz
        $points = $this->guard->attempt($this->an, fn () => $this->an->readPublication($year, $publication), []);

        foreach ($points as $point) {
            $point->key;        // '2024123:42:SOM2', stable from one reading to the next
            $point->title;      // 'Questions au Gouvernement : Fret ferroviaire'
            $point->date;
            foreach ($point->interventions as $intervention) {
                $intervention->speaker;   // 'M. Ugo Bernalicis', null for the report's own words
                $intervention->record;    // '720430', the member's record; null for the chair and the ministers
                $intervention->text;
            }
        }
    }
}
```

A section with paragraphs of its own is a point, a sub-section read under its section's title. `extractReports()` gives the files instead, for an app that reads them itself.

```yaml
wexample_symfony_remote_dila:
  cache_dir: '%kernel.project_dir%/var/remote/dila'
  an_debates:
    url: 'https://echanges.dila.gouv.fr/OPENDATA/Debats/AN/'
    attempts: 3
    retry_delay: 5      # seconds, times the attempt number
```
