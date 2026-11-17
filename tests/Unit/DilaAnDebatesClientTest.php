<?php

namespace Wexample\SymfonyRemoteDila\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wexample\PhpRemote\Enum\RemoteState;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteDila\Service\AnReportParser;
use Wexample\SymfonyRemoteDila\Service\DilaAnDebatesClient;

class DilaAnDebatesClientTest extends TestCase
{
    private function client(MockHttpClient $httpClient): DilaAnDebatesClient
    {
        return new DilaAnDebatesClient($httpClient, new AnReportParser(), new Filesystem(), sys_get_temp_dir().'/dila-tests', 'https://dila.example/AN/', 2, 0);
    }

    public function testTheIndexGivesTheYearsAndPublications(): void
    {
        $client = $this->client(new MockHttpClient([
            new MockResponse('<a href="2011/">2011/</a> <a href="2024/">2024/</a> <a href="Schema-documentation/">'),
            new MockResponse('<a href="AN_2024001.taz">AN_2024001.taz</a><a href="AN_2024002.taz">'),
        ]));

        $this->assertSame([2011, 2024], $client->listYears());
        $this->assertSame(['AN_2024001.taz', 'AN_2024002.taz'], $client->listPublications(2024));
    }

    public function testAnIndexWithoutYearsIsAFormatChange(): void
    {
        $this->expectException(RemoteException::class);

        $this->client(new MockHttpClient(new MockResponse('<html>Maintenance</html>')))->listYears();
    }

    public function testADownloadIsTriedAgainThenFails(): void
    {
        $client = $this->client(new MockHttpClient([new MockResponse('', ['http_code' => 503]), new MockResponse('<a href="2024/">')]));
        $this->assertSame([2024], $client->listYears());

        $this->expectException(RemoteException::class);
        $this->client(new MockHttpClient(array_fill(0, 2, new MockResponse('', ['http_code' => 503]))))->listYears();
    }

    public function testTheStatusSaysWhatIsPublished(): void
    {
        $status = $this->client(new MockHttpClient(new MockResponse('<a href="2011/"> <a href="2026/">')))->checkStatus();

        $this->assertSame(RemoteState::Up, $status->state);
        $this->assertSame('2 years published, the last 2026.', $status->message);
    }
}
