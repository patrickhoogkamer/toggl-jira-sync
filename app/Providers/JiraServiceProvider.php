<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Configuration\ConfigurationInterface;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Project\ProjectService;

class JiraServiceProvider extends ServiceProvider
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
        $this->app->bind(ConfigurationInterface::class, function () {
            return new ArrayConfiguration([
                'jiraHost' => config('services.jira.host'),
                'jiraUser' => config('services.jira.user'),
                'jiraPassword' => config('services.jira.api_token'),
            ]);
        });
    }
}
