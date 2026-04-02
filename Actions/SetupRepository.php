<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\ServerFeatures\Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SetupRepository extends Action
{
    public function name(): string
    {
        return 'Setup Repository';
    }

    public function active(): bool
    {
        return true;
    }

    public function form(): DynamicForm
    {
        return DynamicForm::make([
            DynamicField::make('alert')
                ->alert()
                ->link('TuxCare PHP ELS Docs', 'https://docs.tuxcare.com/els-for-runtimes/php/')
                ->description('Enter your TuxCare license key to set up the PHP ELS repository. If the repository is already set up, this will re-run the setup.'),
            DynamicField::make('license_key')
                ->text()
                ->label('License Key')
                ->placeholder('XXX-XXXXXXXXXXXX')
                ->description('Your TuxCare PHP ELS license key'),
        ]);
    }

    public function handle(Request $request): void
    {
        Validator::make($request->all(), [
            'license_key' => ['required', 'string'],
        ])->validate();

        $this->server->ssh()->exec(
            view('php-els::ssh.setup-repo', [
                'licenseKey' => $request->input('license_key'),
            ]),
            'setup-php-els-repo'
        );

        $request->session()->flash('success', 'PHP ELS repository set up successfully!');
    }
}
