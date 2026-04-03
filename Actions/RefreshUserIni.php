<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\SiteFeatures\Action;
use Illuminate\Http\Request;

class RefreshUserIni extends Action
{
    public function name(): string
    {
        return 'Sync .user.ini';
    }

    public function active(): bool
    {
        return true;
    }

    public function form(): ?DynamicForm
    {
        return null;
    }

    public function handle(Request $request): void
    {
        $path = $this->site->getWebDirectoryPath().'/.user.ini';

        try {
            $content = $this->site->server->os()->readFile($path);
        } catch (SSHError $e) {
            $request->session()->flash('error', 'Failed to read .user.ini: '.$e->getMessage());

            return;
        }

        $this->site->type_data = array_merge($this->site->type_data ?? [], [
            'user_ini_content' => $content,
        ]);
        $this->site->save();

        $request->session()->flash('success', '.user.ini refreshed successfully.');
    }
}
