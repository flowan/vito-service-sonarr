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
        $ssh = $this->service->server->ssh();

        $ssh->exec('sudo apt-get update -y', 'apt-update');
        $ssh->exec('sudo apt-get install -y curl sqlite3 wget', 'install-prereqs');

        if ($this->status() === 'running') {
            $this->stop();
            $this->disable();
        }

        $ssh->exec("sudo mkdir -p $this->dataDirectory && sudo chmod 775 $this->dataDirectory", 'create-directories');

        $arch = trim($ssh->exec('dpkg --print-architecture', 'detect-architecture'));

        $url = 'https://services.sonarr.tv/v1/download/main/latest?version=4&os=linux';
        $url = match ($arch) {
            'amd64' => $url.'&arch=x64',
            'armhf' => $url.'&arch=arm',
            'arm64' => $url.'&arch=arm64',
            default => throw new \Exception("Unsupported architecture: $arch"),
        };

        $ssh->exec('sudo rm -f Sonarr.*.tar.gz', 'cleanup-old-tarballs');
        $ssh->exec("sudo wget --content-disposition \"$url\"", 'download-sonarr');
        $ssh->exec('sudo tar -xvzf Sonarr.*.tar.gz', 'extract-sonarr');

        $ssh->exec("sudo rm -rf $this->binDirectory", 'remove-old-bin');
        $ssh->exec("sudo mv Sonarr $this->installDirectory", 'move-sonarr');
        $ssh->exec("sudo chmod 775 $this->binDirectory && sudo chown vito:vito -R $this->binDirectory", 'set-permissions');
        $ssh->exec('sudo rm -f Sonarr.*.tar.gz', 'cleanup-tarball');
        $ssh->exec('sudo rm -rf /etc/systemd/system/sonarr.service', 'remove-old-service');

        $ssh->exec("cat <<EOF | sudo tee /etc/systemd/system/sonarr.service >/dev/null
[Unit]
Description=Sonarr Daemon
After=syslog.target network.target
[Service]
User=vito
Group=vito
UMask=0002
Type=simple
ExecStart=$this->binDirectory/Sonarr -nobrowser -data=$this->dataDirectory
TimeoutStopSec=20
KillMode=process
Restart=on-failure
[Install]
WantedBy=multi-user.target
EOF", 'create-systemd-service');

        $ssh->exec('sudo systemctl -q daemon-reload', 'reload-systemd');
        $ssh->exec('sudo systemctl -q enable --now -q sonarr', 'enable-start-sonarr');

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
