<?php

namespace Wexample\SymfonyRemoteDila\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteDila\Service\AnReportParser;
use Wexample\SymfonyRemoteDila\Service\DilaAnDebatesClient;

class AnReportParserTest extends TestCase
{
    public function testAReportIsReadIntoItsPoints(): void
    {
        $points = (new AnReportParser())->parseFile(__DIR__.'/../Fixtures/CRI_20241113.xml');
        $point = end($points);

        $this->assertSame('2024123:42:SOM2', $point->key);
        $this->assertSame('Questions au Gouvernement : Fret ferroviaire', $point->title);
        $this->assertSame('2024-11-13', $point->date->format('Y-m-d'));
        $this->assertCount(3, $point->interventions);

        [$chair, $member, $minister] = $point->interventions;
        $this->assertSame('Mme la présidente', $chair->speaker);
        $this->assertNull($chair->record);
        $this->assertSame('M. Ugo Bernalicis', $member->speaker);
        $this->assertSame('720430', $member->record);
        $this->assertSame("Monsieur le ministre, le fret recule.\nIl recule encore.", $member->text);
        $this->assertSame('M. François Durovray', $minister->speaker);
        $this->assertSame('Il avance.', $minister->text);
    }

    public function testAFileOfAnotherFormatIsARemoteFailure(): void
    {
        try {
            (new AnReportParser())->parseFile(__DIR__.'/../Fixtures/CRI_broken.xml');
            $this->fail('No exception.');
        } catch (RemoteException $exception) {
            $this->assertSame(DilaAnDebatesClient::KEY, $exception->remoteKey);
        }
    }
}
