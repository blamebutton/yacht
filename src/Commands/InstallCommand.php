<?php

namespace Blamebutton\Yacht\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Yaml\Yaml;

class InstallCommand extends Command
{
    protected $name = 'yacht:install';

    protected $description = 'Add Docker labels for Traefik proxy';

    public function handle(Application $application): int
    {
        $path = $this->getDockerComposePath($application);
        $yaml = $this->parseDockerCompose($path);

        Arr::set($yaml, 'networks.yacht', $this->getNetwork());

        $services = $this->configureServices(Arr::get($yaml, 'services', []));
        Arr::set($yaml, 'services', $services);

        $this->save($yaml, $path);

        return self::SUCCESS;
    }

    public function configureServices(array $services): array
    {
        foreach ($services as $name => &$service) {
            if ($name === 'laravel.test') {
                $domain = $this->getDomain();

                Arr::set($service, 'labels', $this->getLabels($domain));
                Arr::set($service, 'networks', $this->getNetworks($service));
                Arr::set($service, 'ports', [':80']);
            } else {
                Arr::forget($service, 'ports');
            }
        }

        return $services;
    }

    /**
     * Prompt the user for a domain to use.
     *
     * @return string
     */
    public function getDomain(): string
    {
        $domain = $this->argument('domain');

        while (!$domain = Str::slug($domain)) {
            $domain = $this->ask('Domain (sub-domain of "localhost")');
            $this->input->setArgument('domain', $domain);
        }

        return $domain;
    }

    /**
     * Get the list of networks that a service should be in, when exposed to Traefik.
     *
     * @param array $service
     * @return array
     */
    public function getNetworks(array $service): array
    {
        return Collection::make(Arr::get($service, 'networks', []))
            ->add('yacht')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get the labels associated with the "laravel.test" container.
     *
     * @param string $domain the domain name
     * @return string[] labels
     */
    public function getLabels(string $domain): array
    {
        $slug = Str::slug($domain);

        return [
            sprintf('traefik.http.routers.%s.rule=Host(`%s.localhost`)', $slug, $domain),
        ];
    }

    public function save(array $yaml, string $path): void
    {
        $content = Yaml::dump($yaml, 4);
        file_put_contents($path, $content);
    }

    /**
     * @return array
     */
    public function getNetwork(): array
    {
        return [
            'name' => 'yacht-proxy',
            'external' => true,
        ];
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function parseDockerCompose(string $path): mixed
    {
        return Yaml::parseFile($path);
    }

    /**
     * @param Application $application
     * @return string
     */
    public function getDockerComposePath(Application $application): string
    {
        return $application->basePath('docker-compose.yml');
    }

    protected function getArguments(): array
    {
        return [
            new InputArgument('domain', InputArgument::OPTIONAL, 'Sub-domain of "localhost" (i.e. "my-app.localhost")'),
        ];
    }
}
