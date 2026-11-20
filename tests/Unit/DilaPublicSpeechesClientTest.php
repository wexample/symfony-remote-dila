<?php

namespace Wexample\SymfonyRemoteDila\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteDila\Service\DilaPublicSpeechesClient;
use Wexample\SymfonyRemoteDila\Service\PublicSpeechParser;

class DilaPublicSpeechesClientTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/dila-speeches-tests-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    private function client(MockHttpClient $httpClient): DilaPublicSpeechesClient
    {
        return new DilaPublicSpeechesClient($httpClient, new PublicSpeechParser(), new Filesystem(), $this->cacheDir, 'https://dila.example/vp_discours.json', 2, 0);
    }

    private function file(string ...$ids): string
    {
        return json_encode(array_map(static fn (string $id): array => [
            'id' => $id,
            'titre' => 'Déclaration '.$id,
            'url' => 'https://www.vie-publique.fr/discours/'.$id,
            'prononciation' => '2026-11-17',
            'intervenants' => [['nom' => 'Emmanuel Macron', 'qualite' => 'Président de la République']],
        ], $ids));
    }

    public function testTheFileIsDownloadedOnceAndReadARecordAtATime(): void
    {
        $client = $this->client(new MockHttpClient([new MockResponse($this->file('1', '2'))]));

        $this->assertSame(['1', '2'], array_map(static fn ($speech): string => $speech->id, iterator_to_array($client->getSpeeches(), false)));
        // From disk: the mock holds no second answer.
        $this->assertCount(2, iterator_to_array($client->getSpeeches(), false));
    }

    public function testARefreshDownloadsItAgain(): void
    {
        $client = $this->client(new MockHttpClient([new MockResponse($this->file('1')), new MockResponse($this->file('1', '2', '3'))]));
        iterator_to_array($client->getSpeeches());

        $this->assertCount(3, iterator_to_array($client->getSpeeches(true), false));
    }

    public function testAFailedDownloadLeavesTheFileOnDisk(): void
    {
        $client = $this->client(new MockHttpClient([new MockResponse($this->file('1')), ...array_fill(0, 2, new MockResponse('', ['http_code' => 503]))]));
        iterator_to_array($client->getSpeeches());

        try {
            iterator_to_array($client->getSpeeches(true));
            $this->fail('The download should have failed.');
        } catch (RemoteException) {
        }

        $this->assertCount(1, iterator_to_array($client->getSpeeches(), false));
    }

    public function testAFileThatDoesNotReadIsAFormatChange(): void
    {
        $this->expectException(RemoteException::class);

        iterator_to_array($this->client(new MockHttpClient([new MockResponse('{"error": "maintenance"}')]))->getSpeeches());
    }
}
