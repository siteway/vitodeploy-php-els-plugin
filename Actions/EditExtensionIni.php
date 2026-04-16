<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\Models\Service;
use App\ServerFeatures\Action;
use Illuminate\Http\Request;

class EditExtensionIni extends Action
{
    public function name(): string
    {
        return 'Edit Extension INI';
    }

    public function active(): bool
    {
        return $this->findLoadedService() !== null;
    }

    public function form(): DynamicForm
    {
        $service = $this->findLoadedService();
        $iniFile = $service?->type_data['ext_ini_file'] ?? '';
        $content = $service?->type_data['ext_ini_content'] ?? '';
        $version = $service?->version ?? '';

        return DynamicForm::make([
            DynamicField::make('alert')
                ->alert()
                ->description("Editing: {$iniFile} (PHP {$version})"),
            DynamicField::make('ext_ini_content')
                ->textarea()
                ->label('Extension INI Content')
                ->default($content)
                ->description('Edit the extension INI file content. Changes require PHP-FPM restart to take effect.'),
        ]);
    }

    public function handle(Request $request): void
    {
        $service = $this->findLoadedService();

        if (! $service) {
            $request->session()->flash('error', 'No extension INI file loaded. Use Browse first.');

            return;
        }

        $content = $request->input('ext_ini_content');

        if (! $content) {
            $request->session()->flash('success', 'No changes made.');

            return;
        }

        $iniFile = $service->type_data['ext_ini_file'];
        $versionNumber = str_replace('.', '', $service->version);
        $filePath = "/opt/alt/php{$versionNumber}/etc/php.d/{$iniFile}";

        try {
            $this->server->ssh()->write($filePath, $content, 'root');
        } catch (SSHError $e) {
            $request->session()->flash('error', 'Failed to write file: '.$e->getMessage());

            return;
        }

        // Restart FPM for changes to take effect
        try {
            $this->server->ssh()->exec("sudo systemctl restart alt-php{$versionNumber}-fpm");
        } catch (SSHError) {
            // Non-fatal — file was saved
        }

        $typeData = $service->type_data ?? [];
        $typeData['ext_ini_content'] = $content;
        $service->type_data = $typeData;
        $service->save();

        $request->session()->flash('success', "{$iniFile} updated successfully (PHP {$service->version}).");
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
