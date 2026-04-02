<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\SiteFeatures\Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EditUserIni extends Action
{
    public function name(): string
    {
        return 'Edit .user.ini';
    }

    public function active(): bool
    {
        return $this->userIniExists();
    }

    public function form(): DynamicForm
    {
        $content = '';

        if ($this->userIniExists()) {
            try {
                $content = $this->site->server->os()->readFile($this->userIniPath());
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
        Validator::make($request->all(), [
            'user_ini_content' => ['required', 'string'],
        ])->validate();

        $this->site->server->ssh()->write(
            $this->userIniPath(),
            $request->input('user_ini_content'),
            $this->site->user,
        );

        $request->session()->flash('success', '.user.ini updated successfully.');
    }

    private function userIniPath(): string
    {
        return $this->site->getWebDirectoryPath().'/.user.ini';
    }

    private function userIniExists(): bool
    {
        try {
            $result = $this->site->server->ssh()->exec(
                'test -f '.escapeshellarg($this->userIniPath()).' && echo "EXISTS"'
            );

            return str_contains($result, 'EXISTS');
        } catch (SSHError) {
            return false;
        }
    }
}
