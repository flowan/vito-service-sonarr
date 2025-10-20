<?php

namespace App\Vito\Plugins\Flowan\VitoServiceSonarr\Services;

use App\Actions\FirewallRule\ManageRule;
use App\Services\AbstractService;
use Closure;
use Illuminate\Validation\Rule;

class Sonarr extends AbstractService
{
    protected string $dataDirectory = '/var/lib/sonarr';

    protected string $installDirectory = '/opt';

    protected string $binDirectory = '/opt/Sonarr';

    public static function id(): string
    {
        return 'sonarr';
    }

    public static function type(): string
    {
        return 'automation';
    }

    public function unit(): string
    {
        return 'sonarr';
    }

    public function data(): array
    {
        return [
            'port' => $this->service->type_data['port'] ?? 8989,
            'branch' => $this->service->type_data['branch'] ?? 'main',
        ];
    }

    public function creationData(array $input): array
    {
        return [
            'port' => 8989,
            'branch' => 'main',
        ];
    }

    public function creationRules(array $input): array
    {
        return [
            'type' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    $existingService = $this->service->server->services()
                        ->where('type', self::type())
                        ->where('name', self::id())
                        ->exists();
                    if ($existingService) {
                        $fail('Sonarr is already installed on this server.');
                    }
                },
            ],
            'version' => ['required', Rule::in(['latest'])],
        ];
    }

    public function install(): void
    {
        $this->service->server->ssh()->exec(
            view('vito-service-sonarr::install-sonarr', [
                'branch' => $this->data()['branch'],
            ]),
            'install-sonarr'
        );

        app(ManageRule::class)->create($this->service->server, [
            'name' => 'Sonarr',
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => $this->data()['port'],
            'source_any' => true,
        ]);

        sleep(10); // wait for sonarr to start

        $status = $this->service->server->systemd()->status($this->unit());
        $this->service->validateInstall($status);

        $this->service->type_data = $this->data();
        $this->service->save();

        event('service.installed', $this->service);
        $this->service->server->os()->cleanup();
    }

    public function uninstall(): void
    {
        $this->service->server->ssh()->exec(
            view('vito-service-sonarr::uninstall-sonarr'),
            'uninstall-sonarr'
        );

        if ($rule = $this->service->server->firewallRules()
            ->where('name', 'Sonarr')
            ->where('port', $this->data()['port'])
            ->first()
        ) {
            app(ManageRule::class)->delete($rule);
        }

        event('service.uninstalled', $this->service);
        $this->service->server->os()->cleanup();
    }

    public function enable(): void
    {
        $this->service->server->systemd()->enable($this->unit());
    }

    public function disable(): void
    {
        $this->service->server->systemd()->disable($this->unit());
    }

    public function restart(): void
    {
        $this->service->server->systemd()->restart($this->unit());
    }

    public function stop(): void
    {
        $this->service->server->systemd()->stop($this->unit());
    }

    public function start(): void
    {
        $this->service->server->systemd()->start($this->unit());
    }

    public function status(): string
    {
        try {
            $result = $this->service->server->ssh()->exec('sudo systemctl is-active sonarr');

            return trim($result) === 'active' ? 'running' : 'stopped';
        } catch (\Exception $e) {
            return 'stopped';
        }
    }
}
