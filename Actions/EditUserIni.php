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
        $content = $this->site->type_data['user_ini_content'] ?? null;

        if ($content === null) {
            try {
                $content = $this->site->server->os()->readFile($this->userIniPath());
                $this->site->type_data = array_merge($this->site->type_data ?? [], [
                    'user_ini_content' => $content,
                ]);
                $this->site->save();
            } catch (SSHError) {
                $content = '';
            }
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
                $this->site->user ?? $this->site->server->getSshUser(),
            );
        } catch (SSHError $e) {
            $request->session()->flash('error', 'Failed to write .user.ini: '.$e->getMessage());

            return;
        }

        $this->site->type_data = array_merge($this->site->type_data ?? [], [
            'user_ini_content' => $content,
        ]);
        $this->site->save();

        $request->session()->flash('success', '.user.ini updated successfully.');
    }

    private function userIniPath(): string
    {
        return $this->site->getWebDirectoryPath().'/.user.ini';
    }
}
