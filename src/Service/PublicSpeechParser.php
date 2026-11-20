<?php

namespace Wexample\SymfonyRemoteDila\Service;

use DateTimeImmutable;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteDila\Class\PublicSpeech;
use Wexample\SymfonyRemoteDila\Class\PublicSpeechSpeaker;

/**
 * Reads a record of `vp_discours.json` into a PublicSpeech, tidied of what the
 * file carries along: titles ending on line breaks, `NULL` written as a string,
 * tracking parameters on the addresses.
 *
 * The file names an institution as a speaker now and then — « Présidence de la
 * République », « Ministère de la justice » —: it is read as one of the
 * organizations the speech is issued for, not as someone heard.
 */
class PublicSpeechParser
{
    /**
     * The speakers who are institutions: those the record names as its authors,
     * and these.
     */
    private const string INSTITUTION = '/^(Ministère|Secrétariat|Présidence|Services du|Gouvernement$|Premier ministre$|Première ministre$)/u';

    /**
     * @param array<string, mixed> $item a record as the file holds it
     *
     * @return PublicSpeech|null null for a record without its address or title
     *
     * @throws RemoteException when the record lacks the fields every one has: the format changed
     */
    public function parse(array $item): ?PublicSpeech
    {
        foreach (['id', 'titre', 'url', 'intervenants'] as $field) {
            if (! array_key_exists($field, $item)) {
                throw new RemoteException(DilaPublicSpeechesClient::KEY, sprintf('A public speech has no `%s`: the format changed.', $field));
            }
        }

        $id = $this->text($item['id']);
        $title = $this->text($item['titre']);
        $url = $this->text($item['url']);

        if (null === $id || null === $title || null === $url) {
            return null;
        }

        $organizations = $this->texts($item['auteur_moral'] ?? []);
        $speakers = [];

        foreach ($item['intervenants'] as $speaker) {
            $name = $this->text($speaker['nom'] ?? null);

            if (null === $name || isset($speakers[$name])) {
                continue;
            }

            if (in_array($name, $organizations, true) || 1 === preg_match(self::INSTITUTION, $name)) {
                $organizations[] = $name;

                continue;
            }

            $speakers[$name] = new PublicSpeechSpeaker($name, $this->text($speaker['qualite'] ?? null));
        }

        return new PublicSpeech(
            $id,
            $title,
            // `?egn-publisher=…`: vie-publique's own statistics.
            preg_replace('/\?egn-[^#]*$/', '', $url),
            $this->date($item['prononciation'] ?? null),
            $this->text($item['type_document'] ?? null),
            $this->text($item['type_emetteur'] ?? null),
            array_values($speakers),
            array_values(array_unique($organizations)),
            $this->text($item['circonstance'] ?? null),
            $this->text($item['media'] ?? null),
            $this->texts($item['thematiques'] ?? []),
            $this->texts($item['descripteurs'] ?? []),
            $this->date($item['mise_a_jour'] ?? null),
        );
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return '' === $value || 'NULL' === $value ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function texts(mixed $values): array
    {
        return is_array($values) ? array_values(array_unique(array_filter(array_map($this->text(...), $values)))) : [];
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        $value = $this->text($value);

        return null === $value ? null : (DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null);
    }
}
