<?php

namespace App\Vito\Plugins\Flowan\VitoServiceSonarr;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterServiceType;
use App\Plugins\RegisterViews;
use App\Vito\Plugins\Flowan\VitoServiceSonarr\Services\Sonarr;
use Illuminate\Support\Facades\Artisan;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Sonarr';

    protected string $description = 'Sonarr is a PVR for Usenet and BitTorrent users.';

    public function boot(): void
    {
        RegisterViews::make('vito-service-sonarr')
            ->path(__DIR__.'/views')
            ->register();

        RegisterServiceType::make('sonarr')
            ->type(Sonarr::type())
            ->label($this->name)
            ->handler(Sonarr::class)
            ->versions([
                'latest',
            ])
            ->register();
    }

    public function enable(): void
    {
        // Temporary fix until this is fixed in vito, see https://github.com/vitodeploy/vito/issues/842
        dispatch(fn () => Artisan::call('horizon:terminate'));
    }
}
