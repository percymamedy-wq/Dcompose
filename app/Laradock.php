<?php

namespace App;

use ZipArchive;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Collection;
use App\Support\Artifacts\EnvFile;

class Laradock
{
    /**
     * GitHub API client.
     *
     * @var Client
     */
    protected $gitHubClient;

    /**
     * The laradock zip path.
     *
     * @var string
     */
    protected $laradockZip;

    /**
     * Satchel instance.
     *
     * @var Satchel
     */
    protected $satchel;

    /**
     * Laradock constructor.
     *
     * @param  Client   $gitHubClient
     * @param  string   $laradockZip
     * @param  Satchel  $satchel
     */
    public function __construct(Client $gitHubClient, string $laradockZip, Satchel $satchel)
    {
        $this->laradockZip = $laradockZip;
        $this->gitHubClient = $gitHubClient;
        $this->satchel = $satchel;
    }

    /**
     * Download and unzip laradock.
     *
     * @return Laradock
     */
    public function grabAndPutInSatchel(): Laradock
    {
        return $this->downloadZip()->unzip();
    }

    /**
     * Get available services.
     *
     * @return Collection
     */
    public function availableServices(): Collection
    {
        return $this->satchel->laradockData();
    }

    /**
     * Checks if service is avalable in laradock.
     *
     * @param  string  $service
     *
     * @return bool
     */
    public function has(string $service): bool
    {
        return $this->availableServices()->contains($service);
    }

    /**
     * Get the contents of the docker-compose.yml.
     *
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function dockerComposeData()
    {
        return Yaml::parse($this->satchel->get('laradock/data/docker-compose.yml'));
    }

    /**
     * Get the contents of the .env file.
     *
     * @return EnvFile|null
     */
    public function envData()
    {
        try {
            return EnvFile::load(
                $this->satchel->get('laradock/data/env-example')
            );
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            return null;
        }
    }

    /**
     * Get the full path of the service.
     *
     * @param  string  $service
     *
     * @return string
     */
    public function servicePath(string $service): string
    {
        return $this->satchel->path('laradock/data/' . $service);
    }

    /**
     * Unzip Laradock zip file.
     *
     * @return Laradock
     *
     * @throws \InvalidArgumentException
     */
    protected function unzip(): Laradock
    {
        $zip = new ZipArchive();
        $resource = $zip->open($this->laradockZip);

        // Check if we were able to get
        // resource.
        if ($resource === true) {
            // Extract the archive.
            $zip->extractTo(home_dir() . DIRECTORY_SEPARATOR . 'laradock');

            // Delete data directory before if it exists.
            if ($this->satchel->exists('laradock/data')) {
                $this->satchel->deleteDirectory('laradock/data');
            }

            // Rename extracted folder so we may access it later.
            $this->satchel->move(
                Arr::first($this->satchel->directories('laradock')),
                'laradock/data'
            );
            return $this;
        }

        throw new \InvalidArgumentException('Cannot open laradock zip');
    }

    /**
     * Downloads the laradock zip.
     *
     * @return self
     */
    protected function downloadZip(): Laradock
    {
        $this->gitHubClient->get('/repos/laradock/laradock/zipball', ['sink' => $this->laradockZip]);

        return $this;
    }
}
