<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Models\Site;
use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterServerFeature;
use App\Plugins\RegisterServerFeatureAction;
use App\Plugins\RegisterServiceType;
use App\Plugins\RegisterSiteFeature;
use App\Plugins\RegisterSiteFeatureAction;
use App\Plugins\RegisterSiteType;
use App\Plugins\RegisterViews;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions\EditUserIni;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions\InstallExtension;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions\RefreshUserIni;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions\SetupRepository;
use App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions\UpdateElsPhpVersion;

class Plugin extends AbstractPlugin
{
    protected string $name = 'PHP ELS';

    protected string $description = 'TuxCare Extended Lifecycle Support for PHP - install security-patched EOL PHP versions.';

    public function boot(): void
    {
        RegisterViews::make('php-els')
            ->path(__DIR__.'/views')
            ->register();

        RegisterServiceType::make(PhpEls::id())
            ->type(PhpEls::type())
            ->label('PHP ELS')
            ->handler(PhpEls::class)
            ->versions([
                '8.3',
                '8.2',
                '8.1',
                '8.0',
                '7.4',
                '7.3',
                '7.2',
                '7.1',
                '7.0',
                '5.6',
            ])
            ->register();

        RegisterSiteType::make(PhpElsBlank::id())
            ->label('PHP ELS Blank')
            ->handler(PhpElsBlank::class)
            ->form(DynamicForm::make([
                DynamicField::make('els_php_version')
                    ->select()
                    ->label('PHP ELS Version')
                    ->options([
                        '8.3',
                        '8.2',
                        '8.1',
                        '8.0',
                        '7.4',
                        '7.3',
                        '7.2',
                        '7.1',
                        '7.0',
                        '5.6',
                    ])
                    ->description('Select an installed PHP ELS version'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
            ]))
            ->register();

        if (! config('server.features.php-els')) {
            RegisterServerFeature::make('php-els')
                ->label('PHP ELS')
                ->description('TuxCare Extended Lifecycle Support for PHP')
                ->register();
        }

        if (! config('server.features.php-els.actions.setup-repo')) {
            RegisterServerFeatureAction::make('php-els', 'setup-repo')
                ->label('Setup Repository')
                ->handler(SetupRepository::class)
                ->form(DynamicForm::make([
                    DynamicField::make('alert')
                        ->alert()
                        ->link('TuxCare PHP ELS Docs', 'https://docs.tuxcare.com/els-for-runtimes/php/')
                        ->description('Enter your TuxCare license key to set up the PHP ELS repository. If the repository is already set up, this will re-run the setup.'),
                    DynamicField::make('license_key')
                        ->text()
                        ->label('License Key')
                        ->placeholder('XXX-XXXXXXXXXXXX')
                        ->description('Your TuxCare PHP ELS license key'),
                ]))
                ->register();
        }

        if (! config('site.types.php-els-blank.features.php-els-version')) {
            RegisterSiteFeature::make('php-els-blank', 'php-els-version')
                ->label('PHP ELS Version')
                ->description('Change the PHP ELS version used by this site')
                ->register();
        }

        if (! config('site.types.php-els-blank.features.php-els-version.actions.update')) {
            RegisterSiteFeatureAction::make('php-els-blank', 'php-els-version', 'update')
                ->label('Update')
                ->handler(UpdateElsPhpVersion::class)
                ->register();
        }

        // Set active flags directly so buttons are enabled even if handler
        // classes can't be autoloaded (namespace/path mismatch from GitHub install)
        config(['server.features.php-els.actions.setup-repo.active' => true]);

        // Clean up FPM pool and isolated user when a php-els site is deleted
        Site::deleting(function (Site $site): void {
            if ($site->isIsolated() && $site->type()->language() === 'php-els' && $site->php_version) {
                $phpElsService = $site->server->service('php-els', $site->php_version);
                if ($phpElsService) {
                    $phpElsService->handler()->removeFpmPool($site->user, $site->php_version, $site->id);
                }
                $site->server->os()->deleteIsolatedUser($site->user);
            }
        });

        if (! config('site.types.php-els-blank.features.user-ini')) {
            RegisterSiteFeature::make('php-els-blank', 'user-ini')
                ->label('.user.ini')
                ->description('Edit the .user.ini file in the site web directory')
                ->register();
        }

        if (! config('site.types.php-els-blank.features.user-ini.actions.edit')) {
            RegisterSiteFeatureAction::make('php-els-blank', 'user-ini', 'edit')
                ->label('Edit')
                ->handler(EditUserIni::class)
                ->register();
        }

        if (! config('site.types.php-els-blank.features.user-ini.actions.refresh')) {
            RegisterSiteFeatureAction::make('php-els-blank', 'user-ini', 'refresh')
                ->label('Sync')
                ->handler(RefreshUserIni::class)
                ->register();
        }

        if (! config('server.features.php-els.actions.install-extension')) {
            RegisterServerFeatureAction::make('php-els', 'install-extension')
                ->label('Install Extension')
                ->handler(InstallExtension::class)
                ->form(DynamicForm::make([
                    DynamicField::make('version')
                        ->select()
                        ->label('PHP ELS Version')
                        ->options([
                            '8.3',
                            '8.2',
                            '8.1',
                            '8.0',
                            '7.4',
                            '7.3',
                            '7.2',
                            '7.1',
                            '7.0',
                            '5.6',
                        ])
                        ->description('Select the PHP ELS version to install the extension for'),
                    DynamicField::make('extension')
                        ->text()
                        ->label('Extension Name')
                        ->placeholder('e.g. mysqlnd, xml, gd')
                        ->description('The extension package name (without the alt-phpXY- prefix)'),
                ]))
                ->register();
        }
    }
}
