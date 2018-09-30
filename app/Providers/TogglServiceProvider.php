<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MorningTrain\TogglApi\TogglApi;

class TogglServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(TogglApi::class, function () {
            return new TogglApi(config('services.toggl.api_key'));
        });
    }
}
