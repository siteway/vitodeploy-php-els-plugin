<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Models\Service;
use App\ServerFeatures\Action;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\PhpEls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InstallExtension extends Action
{
    public function name(): string
    {
        return 'Install Extension';
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
        $versions = $this->server->services()
            ->where('type', 'php-els')
            ->where('status', 'ready')
            ->pluck('version')
            ->toArray();

        return DynamicForm::make([
            DynamicField::make('version')
                ->select()
                ->label('PHP ELS Version')
                ->options($versions)
                ->description('Select the PHP ELS version to install the extension for'),
            DynamicField::make('extension')
                ->text()
                ->label('Extension Name')
                ->placeholder('e.g. mysqlnd, xml, gd')
                ->description('The extension package name (without the alt-phpXY- prefix)'),
        ]);
    }

    public function handle(Request $request): void
    {
        Validator::make($request->all(), [
            'version' => [
                'required',
                Rule::exists('services', 'version')
                    ->where('server_id', $this->server->id)
                    ->where('type', 'php-els'),
            ],
            'extension' => ['required', 'string'],
        ])->validate();

        /** @var Service $service */
        $service = $this->server->services()
            ->where('type', 'php-els')
            ->where('version', $request->input('version'))
            ->firstOrFail();

        /** @var PhpEls $handler */
        $handler = $service->handler();
        $handler->installExtension($request->input('extension'));

        $typeData = $service->type_data ?? [];
        $typeData['extensions'] ??= [];
        if (! in_array($request->input('extension'), $typeData['extensions'])) {
            $typeData['extensions'][] = $request->input('extension');
        }
        $service->type_data = $typeData;
        $service->save();

        $request->session()->flash('success', 'Extension installed successfully!');
    }
}
