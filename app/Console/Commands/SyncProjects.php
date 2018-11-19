<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use JiraRestApi\Issue\Issue;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\JqlQuery;
use JiraRestApi\JiraException;
use JiraRestApi\Project\ProjectService;
use MorningTrain\TogglApi\TogglApi;

class SyncProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    /**
     * @var TogglApi
     */
    protected $toggl;
    /**
     * @var IssueService
     */
    protected $jiraIssues;
    /**
     * @var ProjectService
     */
    protected $jiraProjects;

    /**
     * @var int
     */
    protected $togglWorkspaceId;

    /**
     * Create a new command instance.
     *
     * @param TogglApi $toggl
     * @param IssueService $jiraIssues
     * @param ProjectService $jiraProjects
     */
    public function __construct(TogglApi $toggl, IssueService $jiraIssues, ProjectService $jiraProjects)
    {
        parent::__construct();

        $this->toggl = $toggl;
        $this->jiraIssues = $jiraIssues;
        $this->jiraProjects = $jiraProjects;

        $this->togglWorkspaceId = config('services.toggl.workspace_id');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \JiraRestApi\JiraException
     * @throws \JsonMapper_Exception
     */
    public function handle()
    {
        $jiraProjects = collect($this->jiraProjects->getAllProjects());
        $togglProjects = collect($this->toggl->getWorkspaceProjects($this->togglWorkspaceId));
        $togglTasks = collect($this->toggl->getWorkspaceTasks($this->togglWorkspaceId,['active'=>'both']));

        foreach ($jiraProjects as $jiraProject) {
            $this->info("Syncing {$jiraProject->name} ({$jiraProject->key})...");
            $togglProject = $this->getTogglProjectIfExists($togglProjects, $jiraProject);

            if (!$togglProject) {
                $togglProject = $this->createTogglProject($jiraProject);
            }

            $this->info('Issues...');
            $this->syncIssues($jiraProject, $togglProject, $togglTasks->where('pid', $togglProject->id));
            $this->info("Done. \n");
        }
    }

    protected function createTogglProject($jiraProject)
    {
        $togglProject = [
            'wid' => $this->togglWorkspaceId,
            'name' => "{$jiraProject->name} ({$jiraProject->key})",
            'is_private' => false,
        ];

        return $this->toggl->createProject($togglProject);
    }

    /**
     * @param $togglProjects
     * @param $jiraProject
     * @return mixed
     */
    public function getTogglProjectIfExists(Collection $togglProjects, $jiraProject)
    {
        return $togglProjects->filter(function ($value) use ($jiraProject) {
            return str_contains($value->name, "({$jiraProject->key})");
        })->first();
    }

    /**
     * @param $jiraProject
     * @param $togglProject
     * @param $togglTasks
     * @throws \JsonMapper_Exception
     */
    protected function syncIssues($jiraProject, $togglProject, $togglTasks)
    {
        $maxResults = 50;
        $startAt = 0;
        $remainingIssues = null;
        $progressBar = null;

        do {
            try {
                $results = $this->getProjectIssues($jiraProject, $startAt, $maxResults);
            } catch (JiraException $e) {
                $this->error('No results found, JiraException: ' . $e->getMessage());
                continue;
            }

            if (!$progressBar) {
                $progressBar = $this->output->createProgressBar($results->getTotal());
            }

            foreach ($results->getIssues() as $issue) {
                $this->createOrSyncTask($togglProject, $togglTasks, $issue);

                $progressBar->advance();
            }

            if (!$remainingIssues) {
                $remainingIssues = $results->getTotal();
            }

            $remainingIssues -= $maxResults;
            $startAt += $maxResults;

        } while ($remainingIssues > 0);

        if ($progressBar) {
            $progressBar->finish();
            $this->info('');
        }
    }

    /**
     * @param $jiraProject
     * @param $startAt
     * @param $maxResults
     * @return \JiraRestApi\Issue\IssueSearchResult|object
     * @throws JiraException
     * @throws \JsonMapper_Exception
     */
    protected function getProjectIssues($jiraProject, $startAt, $maxResults)
    {
        return $this->jiraIssues->search("project = {$jiraProject->key}", $startAt, $maxResults);
    }

    /**
     * @param $togglProject
     * @param $togglTasks
     * @param $issue
     * @return mixed
     */
    protected function createOrSyncTask($togglProject, Collection $togglTasks, Issue $issue)
    {
        $task = $togglTasks->filter(function ($value) use ($issue) {
            return str_contains($value->name, $issue->key);
        })->first();

        $active = in_array($issue->fields->status->name, ['To Do', 'In Progress']);

        if (!$task) {
            $this->toggl->createTask([
                'name'   => "{$issue->key} {$issue->fields->summary}",
                'pid'    => $togglProject->id,
                'active' => $active
            ]);
        } elseif ($active != $task->active) {
            $this->toggl->updateTask(
                $task->id,
                [
                    "active" => $active
                ]
            );
        }

        return $task;
    }
}
