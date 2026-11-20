<?php

namespace Wexample\SymfonyRemoteDila\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteDila\Service\PublicSpeechParser;

class PublicSpeechParserTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function item(array $fields = []): array
    {
        return $fields + [
            'id' => '263001870',
            'titre' => "Lettre de M. Édouard Geffray,  ministre, à Paris le 17 novembre 2026.\r\n\r\n",
            'url' => 'https://www.vie-publique.fr/discours/305401-edouard-geffray?egn-publisher=dila_vp&egn-name=informer_opendata_discours',
            'domaine' => 'Déclaration',
            'prononciation' => '2026-11-17',
            'intervenants' => [
                ['nom' => 'Édouard Geffray ', 'qualite' => "Ministre de l'éducation nationale", 'qualite_long' => null],
                ['nom' => 'Apolline de Malherbe', 'qualite' => 'Journaliste', 'qualite_long' => null],
                ['nom' => 'Présidence de la République', 'qualite' => null, 'qualite_long' => null],
                ['nom' => null, 'qualite' => null, 'qualite_long' => null],
            ],
            'auteur_moral' => [],
            'circonstance' => null,
            'type_emetteur' => 'Gouvernement',
            'type_document' => 'Lettre',
            'type_media' => null,
            'media' => 'NULL',
            'resume' => null,
            'thematiques' => ["Politique de l'enseignement"],
            'descripteurs' => ['Collège', 'Collège', ''],
            'mise_en_ligne' => '2026-11-19',
            'mise_a_jour' => '2026-11-19',
        ];
    }

    public function testARecordIsReadTidied(): void
    {
        $speech = (new PublicSpeechParser())->parse($this->item());

        $this->assertSame('263001870', $speech->id);
        $this->assertSame('Lettre de M. Édouard Geffray, ministre, à Paris le 17 novembre 2026.', $speech->title);
        $this->assertSame('https://www.vie-publique.fr/discours/305401-edouard-geffray', $speech->url);
        $this->assertSame('2026-11-17', $speech->date->format('Y-m-d'));
        $this->assertSame('Lettre', $speech->type);
        $this->assertSame('Gouvernement', $speech->issuer);
        $this->assertNull($speech->media);
        $this->assertSame(["Politique de l'enseignement"], $speech->themes);
        $this->assertSame(['Collège'], $speech->keywords);
        $this->assertSame('2026-11-19', $speech->dateUpdated->format('Y-m-d'));
    }

    public function testAnInstitutionNamedAsASpeakerIsAnOrganization(): void
    {
        $speech = (new PublicSpeechParser())->parse($this->item());

        $this->assertSame(['Édouard Geffray', 'Apolline de Malherbe'], array_map(static fn ($speaker): string => $speaker->name, $speech->speakers));
        $this->assertFalse($speech->speakers[0]->isJournalist());
        $this->assertTrue($speech->speakers[1]->isJournalist());
        $this->assertSame(['Présidence de la République'], $speech->organizations);

        $speech = (new PublicSpeechParser())->parse($this->item([
            'intervenants' => [['nom' => 'Conseil national du parti', 'qualite' => null]],
            'auteur_moral' => ['Conseil national du parti'],
        ]));
        $this->assertSame([], $speech->speakers);
        $this->assertSame(['Conseil national du parti'], $speech->organizations);
    }

    public function testARecordWithoutItsAddressIsLeftOut(): void
    {
        $this->assertNull((new PublicSpeechParser())->parse($this->item(['url' => null])));
    }

    public function testARecordWithoutItsFieldsIsAFormatChange(): void
    {
        $item = $this->item();
        unset($item['intervenants']);

        $this->expectException(RemoteException::class);
        (new PublicSpeechParser())->parse($item);
    }
}
