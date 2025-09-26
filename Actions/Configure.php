<?php

namespace App\Vito\Plugins\Flowan\VitoServiceSonarr\Actions;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\ServerFeatures\Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Configure extends Action
{
    public function name(): string
    {
        return 'Configure';
    }

    public function active(): bool
    {
        return true;
    }

    public function form(): ?DynamicForm
    {
        return new DynamicForm([
//             DynamicField::make('url')
//                 ->label('URL')
//                 ->text()
//                 ->description('For reverse proxy support, default is empty (http://server-ip:8989)'),
            DynamicField::make('url')
                ->label('URL')
                ->description($this->publicUrl())
                ->alert(),
            DynamicField::make('authentication')
                ->label('Authentication')
                ->select()
                ->options(['basic', 'forms']),
            DynamicField::make('username')
                ->label('Username')
                ->text(),
            DynamicField::make('password')
                ->label('Password')
                ->text(),
        ]);
    }

    public function handle(Request $request): void
    {
        Validator::make($request->all(), [
            'authentication' => 'required|in:basic,forms',
            'username' => 'required|string',
            'password' => 'required|string',
        ])->validate();

        $apiKey = $this->server->ssh()->exec('cat /var/lib/sonarr/config.xml | grep -oPm1 "(?<=<ApiKey>)[^<]+"', 'get-sonarr-apikey');

        $data = json_encode([
            'id' => 1,
            'authenticationMethod' => $request->input('authentication'),
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'passwordConfirmation' => $request->input('password'),
            'bindAddress' => '*',
            'port' => 8989,
            'instanceName' => 'Sonarr',
            'logSizeLimit' => 1,
            'branch' => 'main',
            'backupInterval' => 7,
            'backupRetention' => 28,
        ]);

        $this->server->ssh()->exec(
            "curl 'http://127.0.0.1:8989/api/v3/config/host/1' --request PUT --header 'Content-Type: application/json' --header 'X-Api-Key: $apiKey' --data '$data'",
            'configure-sonarr'
        );

        $request->session()->flash('success', 'Sonarr configured successfully.');
    }

    protected function publicUrl(): string
    {
        return "http://{$this->server->ip}:8989";
    }
}
