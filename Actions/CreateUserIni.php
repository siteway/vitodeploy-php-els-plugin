<?php

namespace App\Vito\Plugins\Siteway\VitodeployPhpElsPlugin\Actions;

use App\DTOs\DynamicForm;
use App\Exceptions\SSHError;
use App\SiteFeatures\Action;
use Illuminate\Http\Request;

class CreateUserIni extends Action
{
    public function name(): string
    {
        return 'Create .user.ini';
    }

    public function active(): bool
    {
        return true;
    }

    public function form(): ?DynamicForm
    {
        return null;
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

        if (str_contains($result, 'EXISTS')) {
            $request->session()->flash('error', '.user.ini already exists in the web directory.');

            return;
        }

        $defaultContent = implode("\n", [
            'upload_max_filesize = 64M',
            'post_max_size = 64M',
            'memory_limit = 256M',
            'max_execution_time = 300',
            'max_input_time = 300',
            'max_input_vars = 5000',
        ]);

        $this->site->server->ssh()->write(
            $path,
            $defaultContent,
            $this->site->user,
        );

        $request->session()->flash('success', '.user.ini created successfully.');
    }

    private function userIniPath(): string
    {
        return $this->site->getWebDirectoryPath().'/.user.ini';
    }
}
