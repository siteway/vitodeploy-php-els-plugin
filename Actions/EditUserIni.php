<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\SiteFeatures\Action;
use Illuminate\Http\Request;

class EditUserIni extends Action
{
    public function name(): string
    {
        return 'Edit .user.ini';
    }

    public function active(): bool
    {
        return true;
    }

    public function form(): DynamicForm
    {
        $content = '';

        try {
            $content = $this->site->server->os()->readFile($this->userIniPath());
        } catch (SSHError) {
            $content = '';
        }

        return DynamicForm::make([
            DynamicField::make('user_ini_content')
                ->textarea()
                ->label('.user.ini')
                ->default($content)
                ->description('Edit the .user.ini file in the web directory. Changes take effect within 5 minutes (PHP user_ini.cache_ttl).'),
        ]);
    }

    /**
     * @throws SSHError
     */
    public function handle(Request $request): void
    {
        $content = $request->input('user_ini_content');

        if (! $content) {
            $request->session()->flash('success', 'No changes made.');

            return;
        }

        try {
            $this->site->server->ssh()->write(
                $this->userIniPath(),
                $content,
                $this->site->user,
            );
        } catch (SSHError $e) {
            $request->session()->flash('error', 'Failed to write .user.ini: '.$e->getMessage());

            return;
        }

        $request->session()->flash('success', '.user.ini updated successfully.');
    }

    private function userIniPath(): string
    {
        return $this->site->getWebDirectoryPath().'/.user.ini';
    }
}
