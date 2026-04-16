<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\Models\Service;
use App\ServerFeatures\Action;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\PhpEls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BrowseExtensionIni extends Action
{
    public function name(): string
    {
        return 'Browse Extension INI';
    }

    public function active(): bool
    {
        return $this->server->services()
            ->where('type', 'php-els')
            ->where('status', 'ready')
            ->exists();
    }

    public function form(): DynamicForm
    {
        $options = $this->discoverIniFiles();

        return DynamicForm::make([
            DynamicField::make('ini_file')
                ->select()
                ->label('Extension INI File')
                ->options($options)
                ->description('Select a PHP ELS version and extension INI file to load'),
        ]);
    }

    public function handle(Request $request): void
    {
        $parts = explode(' / ', $request->input('ini_file'), 2);

        if (count($parts) !== 2) {
            $request->session()->flash('error', 'Invalid selection.');

            return;
        }

        [$version, $iniFile] = $parts;

        Validator::make(['version' => $version, 'ini_file' => $iniFile], [
            'version' => [
                'required',
                Rule::exists('services', 'version')
                    ->where('server_id', $this->server->id)
                    ->where('type', 'php-els'),
            ],
            'ini_file' => ['required', 'string', 'regex:/^[\w\-\.]+\.ini$/'],
        ])->validate();

        /** @var Service $service */
        $service = $this->server->services()
            ->where('type', 'php-els')
            ->where('version', $version)
            ->firstOrFail();

        $versionNumber = str_replace('.', '', $version);
        $filePath = "/opt/alt/php{$versionNumber}/etc/php.d/{$iniFile}";

        try {
            $content = $this->server->os()->readFile($filePath);
        } catch (SSHError $e) {
            $request->session()->flash('error', 'Failed to read file: '.$e->getMessage());

            return;
        }

        $typeData = $service->type_data ?? [];
        $typeData['ext_ini_file'] = $iniFile;
        $typeData['ext_ini_content'] = $content;
        $service->type_data = $typeData;
        $service->save();

        $request->session()->flash('success', "Loaded {$iniFile} from PHP {$version}.");
    }

    private function discoverIniFiles(): array
    {
        $options = [];

        $services = $this->server->services()
            ->where('type', 'php-els')
            ->where('status', 'ready')
            ->get();

        foreach ($services as $service) {
            $versionNumber = str_replace('.', '', $service->version);
            $path = "/opt/alt/php{$versionNumber}/etc/php.d";

            try {
                $output = $this->server->ssh()->exec(
                    "ls -1 {$path}/*.ini 2>/dev/null | xargs -I{} basename {}",
                );
                $files = array_filter(explode("\n", trim($output)));

                foreach ($files as $file) {
                    $options[] = "{$service->version} / {$file}";
                }
            } catch (SSHError) {
                // Skip versions where we can't list files
            }
        }

        return $options;
    }
}
