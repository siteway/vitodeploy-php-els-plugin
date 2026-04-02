<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\Models\Service;
use App\SiteFeatures\Action;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\PhpEls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateElsPhpVersion extends Action
{
    public function name(): string
    {
        return 'Update PHP ELS Version';
    }

    public function active(): bool
    {
        return true;
    }

    public function form(): DynamicForm
    {
        return DynamicForm::make([
            DynamicField::make('els_php_version')
                ->select()
                ->label('PHP ELS Version')
                ->options($this->installedVersions())
                ->default($this->site->php_version)
                ->description('Select an installed PHP ELS version to switch this site to'),
        ]);
    }

    /**
     * @throws SSHError
     */
    public function handle(Request $request): void
    {
        Validator::make($request->all(), [
            'els_php_version' => [
                'required',
                Rule::exists('services', 'version')
                    ->where('server_id', $this->site->server_id)
                    ->where('type', 'php-els'),
            ],
        ])->validate();

        $newVersion = $request->input('els_php_version');
        $oldVersion = $this->site->php_version;

        if ($oldVersion === $newVersion) {
            $request->session()->flash('success', 'PHP ELS version is already set to '.$newVersion.'.');

            return;
        }

        $oldVersionNumber = str_replace('.', '', $oldVersion);
        $newVersionNumber = str_replace('.', '', $newVersion);

        $webserver = $this->site->webserver();
        $configPath = $this->vhostConfigPath();

        $this->site->server->ssh()->exec(
            view('php-els::ssh.change-els-php-version', [
                'configPath' => $configPath,
                'oldVersion' => $oldVersionNumber,
                'newVersion' => $newVersionNumber,
                'webservice' => $webserver->unit(),
            ]),
            'change-els-php-version',
            $this->site->id
        );

        if ($this->site->isIsolated()) {
            $oldService = $this->site->server->service('php-els', $oldVersion);
            if ($oldService instanceof Service) {
                /** @var PhpEls $oldHandler */
                $oldHandler = $oldService->handler();
                $oldHandler->removeFpmPool($this->site->user, $oldVersion, $this->site->id);
            }

            $newService = $this->site->server->service('php-els', $newVersion);
            if (! $newService instanceof Service) {
                throw new RuntimeException('PHP ELS service not found for version '.$newVersion);
            }
            /** @var PhpEls $newHandler */
            $newHandler = $newService->handler();
            $newHandler->createFpmPool($this->site->user, $newVersion);
        } else {
            $newService = $this->site->server->service('php-els', $newVersion);
            if ($newService instanceof Service) {
                $this->site->server->systemd()->restart($newService->handler()->unit());
            }
        }

        $this->site->php_version = $newVersion;
        $this->site->save();

        $request->session()->flash('success', 'PHP ELS version updated to '.$newVersion.'.');
    }

    private function vhostConfigPath(): string
    {
        $webserverId = $this->site->webserver()::id();

        return match ($webserverId) {
            'caddy' => '/etc/caddy/sites-available/'.$this->site->domain,
            default => '/etc/nginx/sites-available/'.$this->site->domain,
        };
    }

    /**
     * @return array<int, string>
     */
    private function installedVersions(): array
    {
        $versions = [];
        $services = $this->site->server->services()->where('type', 'php-els')->get(['version']);
        foreach ($services as $service) {
            $versions[] = $service->version;
        }

        return $versions;
    }
}
