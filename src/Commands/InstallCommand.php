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
        $domain = $this->getDomain();

        $path = $application->basePath('docker-compose.yml');
        $yaml = Yaml::parseFile($path);

        Arr::set($yaml, 'networks.yacht', [
            'name' => 'yacht-proxy'
        ]);

        $services = Arr::get($yaml, 'services', []);

        foreach ($services as $name => &$service) {
            Arr::forget($service, 'ports');

            if (in_array($name, ['laravel.test', 'meilisearch', 'minio', 'mailhog'])) {
                Arr::set($service, 'networks', $this->getNetworks($service));

                $fqdn = match ($name) {
                    'laravel.test' => $domain,
                    'meilisearch' => "meilisearch.$domain",
                    'minio' => "minio.$domain",
                    'mailhog' => "mailhog.$domain",
                };

                Arr::set($service, 'labels', $this->getLabels($fqdn));
            }
        }

        Arr::set($yaml, 'services', $services);

        $this->save($yaml, $path);

        return self::SUCCESS;
    }

    public function getDomain(): string
    {
        $domain = $this->argument('domain');

        while (!$domain = Str::slug($domain)) {
            $domain = $this->ask('Domain (sub-domain of "localhost")');
            $this->input->setArgument('domain', $domain);
        }

        return $domain;
    }

    public function getNetworks(array $service): array
    {
        return Collection::make(Arr::get($service, 'networks', []))
            ->add('yacht')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

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

    protected function getArguments(): array
    {
        return [
            new InputArgument('domain', InputArgument::OPTIONAL, 'Sub-domain of "localhost" (i.e. "my-app.localhost")'),
        ];
    }
}
