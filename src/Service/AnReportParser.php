<?php

namespace Wexample\SymfonyRemoteDila\Service;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteDila\Class\DebatePoint;
use Wexample\SymfonyRemoteDila\Class\Intervention;

/**
 * Reads a verbatim report of the Assemblée nationale (`CRI_*.xml`, as the DILA
 * publishes it) into its debate points.
 *
 * A file holds the sittings of one publication; a sitting, its sections and
 * sub-sections, each titled. A section holding paragraphs of its own is a point;
 * a paragraph opening on an `Orateur` starts an intervention, the next ones
 * without continue it.
 *
 * A file that is no XML, or holds no `CompteRendu` — the DILA changed its format —,
 * raises a RemoteException.
 */
class AnReportParser
{
    private const array SECTION_TAGS = ['Section', 'SousSection1', 'SousSection2', 'SousSection3', 'SousSection4'];

    /**
     * Characters the reports use for narrow spaces, outside any printable range.
     */
    private const array SPACE_ARTIFACTS = ['&#128;', '&#130;', "\u{0080}", "\u{0082}"];

    /**
     * @return list<DebatePoint>
     */
    public function parseFile(string $path): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML(str_replace(self::SPACE_ARTIFACTS, ' ', file_get_contents($path)), LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        if (! $loaded || 0 === $xpath->query('//CompteRendu')->length) {
            throw new RemoteException(DilaAnDebatesClient::KEY, sprintf('"%s" is no verbatim report the parser knows.', basename($path)));
        }

        $points = [];

        foreach ($xpath->query('//CompteRendu') as $report) {
            $publication = $xpath->evaluate('string(Metadonnees/parution)', $report);
            $sitting = $xpath->evaluate('string(Metadonnees/numSeance)', $report);
            $date = $xpath->evaluate('string(Metadonnees/dateSeance)', $report);

            // Until 2012 the sitting is described once, at the root of the file, its
            // dates with their offset (`2012-01-10+01:00`).
            if ('' === $date) {
                $date = $xpath->evaluate('string(/*/Metadonnees/dateSeance)');
                $sitting = $xpath->evaluate('string(/*/Metadonnees/numSeance)');
                $publication = str_replace('-', '', substr($xpath->evaluate('string(/*/Metadonnees/dateParution)'), 0, 10))
                    .'_'.$xpath->evaluate('string(/*/Metadonnees/numParution)');
            }

            $date = substr($date, 0, 10);

            if ('' === $date) {
                continue;
            }

            foreach ($xpath->query('Contenu//*['.implode(' or ', array_map(static fn (string $tag): string => 'self::'.$tag, self::SECTION_TAGS)).']', $report) as $position => $section) {
                $point = $this->readSection($xpath, $section, $publication, $sitting, $position, new DateTimeImmutable($date));

                if (null !== $point) {
                    $points[] = $point;
                }
            }
        }

        return $points;
    }

    private function readSection(
        DOMXPath $xpath,
        DOMElement $section,
        string $publication,
        string $sitting,
        int $position,
        DateTimeImmutable $date
    ): ?DebatePoint {
        $title = $this->clean($xpath->evaluate('string(TitreStruct/Intitule)', $section));
        // Some recent reports leave the title without its identifier: its rank in
        // the sitting stands for it then.
        $ident = $xpath->evaluate('string(TitreStruct/@Ident)', $section) ?: 'p'.$position;

        // « Alstom » alone says nothing: a sub-section is read under its section.
        if ('Section' !== $section->nodeName && $section->parentNode instanceof DOMElement) {
            $parentTitle = $this->clean($xpath->evaluate('string(TitreStruct/Intitule)', $section->parentNode));

            if ('' !== $parentTitle && '' !== $title) {
                $title = $parentTitle.' : '.$title;
            }
        }
        $interventions = [];

        foreach ($xpath->query('Para', $section) as $paragraph) {
            [$speaker, $text, $deputy] = $this->readParagraph($xpath, $paragraph);

            if ('' === $text) {
                continue;
            }

            if (null !== $speaker || [] === $interventions) {
                $interventions[] = [$speaker, $deputy, $text];
            } else {
                $interventions[array_key_last($interventions)][2] .= "\n".$text;
            }
        }

        if ('' === $title || [] === $interventions) {
            return null;
        }

        return new DebatePoint(
            sprintf('%s:%s:%s', $publication, $sitting, $ident),
            $title,
            $date,
            array_map(static fn (array $intervention): Intervention => new Intervention(...$intervention), $interventions),
        );
    }

    /**
     * @return array{0: ?string, 1: string, 2: ?string} the speaker, null when the paragraph
     *                                      continues the one before, and the words
     */
    private function readParagraph(
        DOMXPath $xpath,
        DOMElement $paragraph
    ): array {
        $speakerNode = $xpath->query('Orateur', $paragraph)->item(0);
        $speaker = null;
        $deputy = null;
        $skip = [];

        if ($speakerNode instanceof DOMElement) {
            // « M. Ugo Bernalicis. » / « Mme Nicole Belloubet, » then her office.
            $speaker = rtrim($this->clean($speakerNode->textContent), '.,: ');

            // A member of the Assemblée links to their record (`…/tribun/fiches_id/720430.asp`),
            // the chair and the ministers to nothing.
            if (preg_match('#fiches_id/(\d+)\.asp#', $speakerNode->getAttribute('href'), $match)) {
                $deputy = $match[1];
            }
            $skip[] = $speakerNode;
            $next = $this->nextElement($speakerNode);

            if ($next instanceof DOMElement && 'QualiteMouvement' === $next->nodeName && str_ends_with($this->clean($speakerNode->textContent), ',')) {
                $skip[] = $next;
            }
        }

        $text = '';

        foreach ($paragraph->childNodes as $child) {
            if (! in_array($child, $skip, true)) {
                $text .= $child->textContent;
            }
        }

        $text = $this->clean($text);

        if ([] !== $skip && isset($skip[1])) {
            $text = ltrim($text, '. ');
        }

        return [$speaker ?: null, $text, $deputy];
    }

    private function nextElement(DOMNode $node): ?DOMNode
    {
        for ($next = $node->nextSibling; null !== $next; $next = $next->nextSibling) {
            if (XML_ELEMENT_NODE === $next->nodeType) {
                return $next;
            }

            if ('' !== trim($next->textContent)) {
                return null;
            }
        }

        return null;
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
    }
}
