<?php

namespace Wexample\SymfonyRemoteDila\Service;

use Generator;
use JsonException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Wexample\Helpers\Helper\JsonHelper;
use Wexample\PhpRemote\Class\RemoteStatus;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\PhpRemote\Interface\RemoteInterface;
use Wexample\SymfonyRemoteDila\Class\PublicSpeech;

/**
 * The public speeches vie-publique.fr records, as the DILA publishes them in
 * open data (Licence Ouverte 2.0, `DISCOURS_PUBLICS/vp_discours.json`): some
 * 150 000 records of the President's speeches since 1974, the Government's
 * since the early eighties, interviews, press conferences, releases, the
 * parties' and the unions'. The records, not the texts: those are read on
 * vie-publique.fr.
 *
 * The file — a JSON array of some 230 MB, made again each night — is kept under
 * the cache directory once downloaded, and read a record at a time. A download
 * that fails, or a file the package can no longer read, raises a
 * RemoteException; the file already on disk stays as it was.
 *
 * The licence asks a reuse to credit the DILA, with the file's address and date.
 */
class DilaPublicSpeechesClient implements RemoteInterface
{
    public const string KEY = 'dila_public_speeches';

    public const string DEFAULT_URL = 'https://echanges.dila.gouv.fr/OPENDATA/DISCOURS_PUBLICS/vp_discours.json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PublicSpeechParser $parser,
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
        return 'DILA, discours publics de vie-publique.fr';
    }

    public function checkStatus(): RemoteStatus
    {
        $headers = $this->httpClient->request('HEAD', $this->url)->getHeaders();

        return RemoteStatus::up('Made '.($headers['last-modified'][0] ?? 'at an unknown date').'.');
    }

    /**
     * @param bool $refresh download the file again, for the speeches since
     *
     * @return Generator<int, PublicSpeech> in the file's order, the latest first
     */
    public function getSpeeches(bool $refresh = false): Generator
    {
        $path = $this->cacheDir.'/vp_discours.json';

        if ($refresh || ! is_file($path)) {
            $this->download($path);
        }

        try {
            foreach (JsonHelper::readArrayItems($path) as $item) {
                if (! is_array($item)) {
                    throw new RemoteException(self::KEY, 'A public speech is no object: the format changed.');
                }

                $speech = $this->parser->parse($item);

                if (null !== $speech) {
                    yield $speech;
                }
            }
        } catch (RemoteException $exception) {
            throw $exception;
        } catch (JsonException|RuntimeException $exception) {
            throw new RemoteException(self::KEY, sprintf('"%s" does not read: %s', basename($path), $exception->getMessage()), $exception);
        }
    }

    /**
     * Streamed aside, then moved in place: a file there is a complete one.
     */
    private function download(string $path): void
    {
        $partial = $path.'.part';
        $this->filesystem->mkdir($this->cacheDir);

        // The DILA's server drops a request now and then: tried again, it answers.
        for ($attempt = 1; ; ++$attempt) {
            $file = fopen($partial, 'w');

            try {
                $response = $this->httpClient->request('GET', $this->url, ['timeout' => 120]);

                foreach ($this->httpClient->stream($response) as $chunk) {
                    fwrite($file, $chunk->getContent());
                }

                fclose($file);
                $this->filesystem->rename($partial, $path, true);

                return;
            } catch (ExceptionInterface $exception) {
                fclose($file);
                $this->filesystem->remove($partial);

                if ($attempt >= $this->attempts) {
                    throw new RemoteException(self::KEY, sprintf('Unable to download "%s": %s', $this->url, $exception->getMessage()), $exception);
                }

                sleep($attempt * $this->retryDelay);
            }
        }
    }
}
