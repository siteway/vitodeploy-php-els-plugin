<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\Models\Service;
use App\ServerFeatures\Action;
use Illuminate\Http\Request;

class SyncExtensionIni extends Action
{
    public function name(): string
    {
        return 'Sync Extension INI';
    }

    public function active(): bool
    {
        return $this->findLoadedService() !== null;
    }

    public function form(): ?DynamicForm
    {
        return null;
    }

    public function handle(Request $request): void
    {
        $service = $this->findLoadedService();

        if (! $service) {
            $request->session()->flash('error', 'No extension INI file loaded. Use Browse first.');

            return;
        }

        $iniFile = $service->type_data['ext_ini_file'];
        $versionNumber = str_replace('.', '', $service->version);
        $filePath = "/opt/alt/php{$versionNumber}/etc/php.d/{$iniFile}";

        try {
            $content = $this->server->os()->readFile($filePath);
        } catch (SSHError $e) {
            $request->session()->flash('error', 'Failed to read file: '.$e->getMessage());

            return;
        }

        $typeData = $service->type_data ?? [];
        $typeData['ext_ini_content'] = $content;
        $service->type_data = $typeData;
        $service->save();

        $request->session()->flash('success', "{$iniFile} synced successfully (PHP {$service->version}).");
    }

    private function findLoadedService(): ?Service
    {
        return $this->server->services()
            ->where('type', 'php-els')
            ->where('status', 'ready')
            ->get()
            ->first(fn (Service $s) => ! empty($s->type_data['ext_ini_file']));
    }
}
