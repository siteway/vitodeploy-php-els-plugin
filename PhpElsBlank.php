<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin;

use App\Exceptions\SSHError;
use App\Models\Service;
use App\Models\Site;
use App\SiteTypes\AbstractSiteType;
use App\Traits\NormalizesWebDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PhpElsBlank extends AbstractSiteType
{
    use NormalizesWebDirectory;

    public static function id(): string
    {
        return 'php-els-blank';
    }

    public static function make(): self
    {
        return new self(new Site(['type' => self::id()]));
    }

    public function language(): string
    {
        return 'php-els';
    }

    public function requiredServices(): array
    {
        return [
            'php-els',
            'webserver',
        ];
    }

    public function createRules(array $input): array
    {
        return [
            'web_directory' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._\-\/]+$/',
                'not_regex:/\.\./',
            ],
            'els_php_version' => [
                'required',
                Rule::in($this->installedPhpElsVersions()),
            ],
        ];
    }

    public function createFields(array $input): array
    {
        return [
            'web_directory' => $this->normalizeWebDirectory($input['web_directory'] ?? ''),
            'php_version' => $input['els_php_version'] ?? '',
        ];
    }

    public function data(array $input): array
    {
        return [];
    }

    /**
     * @throws SSHError
     */
    public function install(): void
    {
        $this->isolateWithElsPhp();
        $this->site->webserver()->createVHost($this->site);
        $this->progress(65);
        $this->restartElsPhp();
    }

    public function baseCommands(): array
    {
        return [];
    }

    public function vhost(string $webserver): string|View
    {
        $version = str_replace('.', '', $this->site->php_version);

        if ($webserver === 'nginx') {
            return view('ssh.services.webserver.nginx.vhost', [
                'header' => [
                    view('ssh.services.webserver.nginx.vhost-blocks.force-ssl', ['site' => $this->site]),
                ],
                'main' => [
                    view('ssh.services.webserver.nginx.vhost-blocks.port', ['site' => $this->site]),
                    view('ssh.services.webserver.nginx.vhost-blocks.core', ['site' => $this->site]),
                    view('php-els::vhost.nginx-php', [
                        'site' => $this->site,
                        'version' => $version,
                    ]),
                    view('ssh.services.webserver.nginx.vhost-blocks.redirects', ['site' => $this->site]),
                ],
            ]);
        }

        if ($webserver === 'caddy') {
            return view('ssh.services.webserver.caddy.vhost', [
                'site' => $this->site,
                'main' => [
                    view('ssh.services.webserver.caddy.vhost-blocks.force-ssl', ['site' => $this->site]),
                    view('ssh.services.webserver.caddy.vhost-blocks.port', ['site' => $this->site]),
                    view('ssh.services.webserver.caddy.vhost-blocks.core', ['site' => $this->site]),
                    view('php-els::vhost.caddy-php', [
                        'site' => $this->site,
                        'version' => $version,
                    ]),
                    view('ssh.services.webserver.caddy.vhost-blocks.redirects', ['site' => $this->site]),
                ],
            ]);
        }

        return '';
    }

    /**
     * @throws SSHError
     */
    protected function isolateWithElsPhp(): void
    {
        if (! $this->site->isIsolated()) {
            return;
        }

        $this->site->server->os()->createIsolatedUser(
            $this->site->user,
            Str::random(15),
            $this->site->id
        );

        if ($this->site->php_version) {
            $service = $this->getElsPhpService();
            if (! $service instanceof Service) {
                throw new RuntimeException('PHP ELS service not found');
            }
            /** @var PhpEls $handler */
            $handler = $service->handler();
            $handler->createFpmPool(
                $this->site->user,
                $this->site->php_version
            );
        }
    }

    private function restartElsPhp(): void
    {
        $service = $this->getElsPhpService();
        if ($service) {
            $this->site->server->systemd()->restart($service->handler()->unit());
        }
    }

    private function getElsPhpService(): ?Service
    {
        return $this->site->server->service('php-els', $this->site->php_version);
    }

    /**
     * @return array<int, string>
     */
    private function installedPhpElsVersions(): array
    {
        $versions = [];
        $services = $this->site->server->services()->where('type', 'php-els')->get(['version']);
        foreach ($services as $service) {
            $versions[] = $service->version;
        }

        return $versions;
    }
}
