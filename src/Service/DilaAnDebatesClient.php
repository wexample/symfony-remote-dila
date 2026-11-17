<?php

namespace Wexample\SymfonyRemoteDila\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Wexample\PhpRemote\Class\RemoteStatus;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\PhpRemote\Interface\RemoteInterface;
use Wexample\SymfonyRemoteDila\Class\DebatePoint;

/**
 * The verbatim reports of the Assemblée nationale as the DILA publishes them in
 * open data (Licence Ouverte): one `.taz` per publication under a directory per
 * year, a tar holding a tar holding the report (`CRI_*.xml`). October 2011 on.
 *
 * Publications are unpacked under the cache directory once downloaded, so that a
 * reading started again takes them from disk. A download is tried again a few
 * times; the last failure, like an archive that does not unpack, raises a
 * RemoteException.
 */
class DilaAnDebatesClient implements RemoteInterface
{
    public const string KEY = 'dila_an_debates';

    public const string DEFAULT_URL = 'https://echanges.dila.gouv.fr/OPENDATA/Debats/AN/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AnReportParser $parser,
        private readonly Filesystem $filesystem,
        private readonly string $cacheDir,
        private readonly string $url = self::DEFAULT_URL,
        private readonly int $attempts = 3,
        private readonly int $retryDelay = 5,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'DILA, débats de l\'Assemblée nationale';
    }

    public function checkStatus(): RemoteStatus
    {
        $years = $this->listYears();

        return RemoteStatus::up(sprintf('%d years published, the last %d.', count($years), max($years)));
    }

    /**
     * @return list<int> the years published, oldest first
     */
    public function listYears(): array
    {
        preg_match_all('#href="(\d{4})/"#', $this->fetch($this->url), $matches);

        if ([] === $matches[1]) {
            throw new RemoteException(self::KEY, 'The index lists no year: its format changed.');
        }

        return array_map('intval', array_values(array_unique($matches[1])));
    }

    /**
     * @return list<string> the publications of a year, as file names (`AN_2019001.taz`)
     */
    public function listPublications(int $year): array
    {
        // `AN_2019001.taz` lately, `AN_20111004_083.taz` until 2012.
        preg_match_all('#href="(AN_[\d_]+\.taz)"#', $this->fetch($this->url.$year.'/'), $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Downloads the publication if it is not on disk yet, unpacks it, and reads
     * its reports.
     *
     * @return list<DebatePoint>
     */
    public function readPublication(
        int $year,
        string $publication
    ): array {
        $points = [];

        foreach ($this->extractReports($year, $publication) as $report) {
            array_push($points, ...$this->parser->parseFile($report));
        }

        return $points;
    }

    /**
     * @return list<string> the paths of the reports the publication holds
     */
    public function extractReports(
        int $year,
        string $publication
    ): array {
        $dir = $this->cacheDir.'/'.$year.'/'.basename($publication, '.taz');

        if (! is_dir($dir)) {
            // Unpacked aside, then moved in place: a directory there is a complete one.
            $partial = $dir.'.part';
            $archive = $dir.'.taz';
            $this->filesystem->remove($partial);
            $this->filesystem->dumpFile($archive, $this->fetch($this->url.$year.'/'.$publication));

            try {
                $this->untar($archive, $partial);

                // The outer tar may hold another one.
                foreach (glob($partial.'/*.tar') ?: [] as $inner) {
                    $this->untar($inner, $partial);
                    $this->filesystem->remove($inner);
                }
            } finally {
                $this->filesystem->remove($archive);
            }

            $this->filesystem->rename($partial, $dir);
        }

        return [...(glob($dir.'/CRI_*.xml') ?: []), ...(glob($dir.'/*/CRI_*.xml') ?: [])];
    }

    /**
     * GNU tar, which reads the older archives too: until 2015 they are compressed
     * with `compress` (.Z), which PharData does not know.
     */
    private function untar(
        string $archive,
        string $to
    ): void {
        $this->filesystem->mkdir($to);

        try {
            (new Process(['tar', '-xf', $archive, '-C', $to]))->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new RemoteException(self::KEY, sprintf('"%s" does not unpack: %s', basename($archive), trim($exception->getProcess()->getErrorOutput())), $exception);
        }
    }

    private function fetch(string $url): string
    {
        // The DILA's server drops a request now and then: tried again, it answers.
        for ($attempt = 1; ; ++$attempt) {
            try {
                return $this->httpClient->request('GET', $url, ['timeout' => 60])->getContent();
            } catch (ExceptionInterface $exception) {
                if ($attempt >= $this->attempts) {
                    throw new RemoteException(self::KEY, sprintf('Unable to download "%s": %s', $url, $exception->getMessage()), $exception);
                }

                sleep($attempt * $this->retryDelay);
            }
        }
    }
}
