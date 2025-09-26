<?php

namespace App\Vito\Plugins\Flowan\VitoServiceSonarr;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterServerFeature;
use App\Plugins\RegisterServerFeatureAction;
use App\Plugins\RegisterServiceType;
use App\Vito\Plugins\Flowan\VitoServiceSonarr\Actions\Configure;
use App\Vito\Plugins\Flowan\VitoServiceSonarr\Services\Sonarr;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Sonarr';

    protected string $description = 'Sonarr is a PVR for Usenet and BitTorrent users.';

    public function boot(): void
    {
        RegisterServiceType::make('sonarr')
            ->type(Sonarr::type())
            ->label($this->name)
            ->handler(Sonarr::class)
            ->versions([
                'latest',
            ])
            ->register();

        RegisterServerFeature::make('sonarr')
            ->label($this->name)
            ->description('Configure Sonarr on your server.')
            ->register();

        RegisterServerFeatureAction::make('sonarr', 'configure')
            ->label('Configure')
            ->handler(Configure::class)
            ->register();
    }
}
