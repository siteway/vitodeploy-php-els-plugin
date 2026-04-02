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
        return DynamicForm::make([
            DynamicField::make('user_ini_content')
                ->textarea()
                ->label('.user.ini')
                ->description('Edit the .user.ini file in the web directory. Changes take effect within 5 minutes (PHP user_ini.cache_ttl).'),
        ]);
    }

    /**
     * @throws SSHError
     */
    public function handle(Request $request): void
    {
        $path = $this->userIniPath();

        try {
            $result = $this->site->server->ssh()->exec(
                'test -f '.escapeshellarg($path).' && echo "EXISTS"'
            );
        } catch (SSHError) {
            $request->session()->flash('error', 'Failed to check .user.ini file.');

            return;
        }

        if (! str_contains($result, 'EXISTS')) {
            $request->session()->flash('error', 'No .user.ini file found. Use "Create" to create one first.');

            return;
        }

        $content = $request->input('user_ini_content');

        if ($content) {
            $this->site->server->ssh()->write(
                $path,
                $content,
                $this->site->user,
            );

            $request->session()->flash('success', '.user.ini updated successfully.');

            return;
        }

        $request->session()->flash('success', 'No changes made.');
    }

    private function userIniPath(): string
    {
        return $this->site->getWebDirectoryPath().'/.user.ini';
    }
}
