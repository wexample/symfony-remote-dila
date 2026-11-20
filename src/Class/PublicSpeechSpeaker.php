<?php

namespace Wexample\SymfonyRemoteDila\Class;

/**
 * Someone heard in a public speech, as vie-publique.fr names them.
 */
final readonly class PublicSpeechSpeaker
{
    /**
     * @param string      $name    their name (« Édouard Geffray »)
     * @param string|null $quality what they spoke as (« Ministre de l'éducation nationale », « Journaliste »)
     */
    public function __construct(
        public string $name,
        public ?string $quality = null,
    ) {
    }

    /**
     * The one asking, in an interview: heard, not speaking for themselves.
     */
    public function isJournalist(): bool
    {
        return null !== $this->quality && str_starts_with(mb_strtolower($this->quality), 'journaliste');
    }
}
