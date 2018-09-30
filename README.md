# Toggl JIRA Sync
Sync JIRA projects and issues (as tasks) to Toggl

Just clone this repository and set the required environment variables: 

```
TOGGL_API_KEY=<api_token>
TOGGL_WORKSPACE_ID=1234

JIRA_HOST=https://company.atlassian.net
JIRA_USER=<username>
JIRA_API_TOKEN=<api_token>
```

Then you can use the artisan command:

```
php artisan projects:sync
```

If you want to run this periodically you can add the command to the `schedule()` method in the console `Kernel` (make sure to set up the cronjob) or just add a cron to run the command manually.
