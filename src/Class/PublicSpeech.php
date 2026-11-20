<?php

namespace Wexample\SymfonyRemoteDila\Class;

use DateTimeImmutable;

/**
 * A public speech as vie-publique.fr records it — a statement, an interview, a
 * press conference, a release — : who spoke, when, as what, about what, and
 * where its text is read. The record holds no text: the DILA publishes the
 * records alone.
 */
final readonly class PublicSpeech
{
    /**
     * @param string                    $id            the DILA's, stable across publications
     * @param string                    $url           its page on vie-publique.fr, where the text is read
     * @param string|null               $type          what it is (« Déclaration », « Interview », « Conférence de presse », « Communiqué »…)
     * @param string|null               $issuer        who issues it (« Gouvernement », « Président de la République », « Partis », « Syndicats Patronat »…)
     * @param list<PublicSpeechSpeaker> $speakers      the people heard, in the order the record gives
     * @param list<string>              $organizations the institutions it speaks for (« Présidence de la République »)
     * @param string|null               $circumstance  the occasion (« Lancement du Tropical Forests Forever Fund »)
     * @param string|null               $media         the programme or paper it was given to (« Emission L'Invité de RTL »)
     * @param list<string>              $themes        the DILA's broad subjects (« Politique de l'enseignement »)
     * @param list<string>              $keywords      its narrower descriptors (« Collège », « Apprentissage »)
     * @param DateTimeImmutable|null    $dateUpdated   when the DILA last changed the record
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public ?DateTimeImmutable $date,
        public ?string $type,
        public ?string $issuer,
        public array $speakers,
        public array $organizations,
        public ?string $circumstance,
        public ?string $media,
        public array $themes,
        public array $keywords,
        public ?DateTimeImmutable $dateUpdated,
    ) {
    }
}
