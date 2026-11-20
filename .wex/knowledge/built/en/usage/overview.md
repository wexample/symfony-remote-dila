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

## The public speeches

```php
public function __construct(private DilaPublicSpeechesClient $speeches, private RemoteGuard $guard) {}

// A generator: the file — some 230 MB — is read a record at a time.
foreach ($this->speeches->getSpeeches(refresh: true) as $speech) {
    $speech->id;            // '263001870', the DILA's
    $speech->title;         // 'Lettre adressée aux parents d'élèves, de M. Édouard Geffray, ministre…'
    $speech->url;           // its page on vie-publique.fr, where the text is read
    $speech->date;          // when it was delivered
    $speech->type;          // 'Déclaration', 'Interview', 'Conférence de presse', 'Communiqué'…
    $speech->issuer;        // 'Gouvernement', 'Président de la République', 'Partis'…
    foreach ($speech->speakers as $speaker) {
        $speaker->name;             // 'Édouard Geffray'
        $speaker->quality;          // 'Ministre de l'éducation nationale', 'Journaliste'
        $speaker->isJournalist();
    }
    $speech->organizations; // ['Présidence de la République'], an institution named as a speaker included
    $speech->themes;        // ['Politique de l'enseignement']
    $speech->keywords;      // ['Collège', 'Apprentissage']
    $speech->dateUpdated;   // when the DILA last changed the record
}
```

The file is downloaded once into `cache_dir/public-speeches`, and again when `refresh` asks: the DILA makes it again each night. A record without its address or title is left out; one without the fields every record has means the format changed, and raises.

```yaml
wexample_symfony_remote_dila:
  cache_dir: '%kernel.project_dir%/var/remote/dila'
  an_debates:
    url: 'https://echanges.dila.gouv.fr/OPENDATA/Debats/AN/'
    attempts: 3
    retry_delay: 5      # seconds, times the attempt number
  public_speeches:
    url: 'https://echanges.dila.gouv.fr/OPENDATA/DISCOURS_PUBLICS/vp_discours.json'
    attempts: 3
    retry_delay: 5
```
