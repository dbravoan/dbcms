<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\ProjectLocalEnvironmentPlan;
use App\DTOs\ProjectProfile;
use App\Services\Projects\ProjectLocalEnvironmentService;
use App\Services\Projects\ProjectSourceBootstrapService;
use App\Services\Projects\ProjectSourceSelection;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

final class ProjectInitCommand extends Command
{
    protected $signature = 'cms:project:init {name? : Project slug (suggested from the directory)}
        {--brand= : Administrative brand ID}
        {--theme= : all, one public theme ID or a CSV list}
        {--modules=all : all, none or module manifest IDs as CSV}
        {--block-packages=all : all, none or block package manifest IDs as CSV}
        {--deploy= : github-actions, ansistrano, rsync, docker or none}
        {--local : Prepare the local environment before the host starts Sail}
        {--prepare-only : Prepare sources only, without starting local services}';

    protected $description = 'Guide project creation from a verified stable release, or resume its recorded preparation';

    public function handle(ProjectSourceBootstrapService $sources, ProjectLocalEnvironmentService $environment): int
    {
        $session = null;
        $cancelled = false;
        try {
            if ($this->option('local') && $this->option('prepare-only')) {
                throw new DomainException('Choose either --local or --prepare-only.');
            }
            $root = base_path();
            if (! $this->input->isInteractive() && ! is_file($root.'/cms-project.json')) {
                foreach (['brand', 'theme', 'deploy'] as $required) {
                    if (! is_string($this->option($required)) || $this->option($required) === '') {
                        throw new DomainException('Noninteractive initialization requires name, --brand, --theme and --deploy.');
                    }
                }
                if (! is_string($this->argument('name')) || $this->argument('name') === '') {
                    throw new DomainException('Noninteractive initialization requires name, --brand, --theme and --deploy.');
                }
            }
            $this->line(is_file($root.'/cms-project.json') ? 'Resuming the recorded project; its version and composition are preserved.' : 'Resolving and verifying the latest published stable dbCMS release…');
            $session = $sources->prepare($root);
            $localPlan = null;
            if ($session->existingProfile !== null) {
                $profile = $session->existingProfile;
                $this->assertRecordedArguments($profile, $root, $sources->choices($session)['themes']);
                $this->showProfile($profile);
                if ($this->option('local')) {
                    $localPlan = $environment->plan($root, $profile);
                    $this->showLocal($localPlan);
                    $this->confirmPlan($cancelled);
                }
            } else {
                $choices = $sources->choices($session);
                [$name, $brand, $themes, $deployment, $modules, $packages] = $this->selections($choices, $root);
                $profile = $sources->apply($session, $name, $brand, $themes, $deployment, $modules, $packages, function (ProjectProfile $profile, array $identifiers, array $changes) use ($environment, $root, $choices, $modules, &$localPlan, &$cancelled): void {
                    $this->showProfile($profile);
                    foreach (['modules' => 'Modules', 'block_packages' => 'Block packages'] as $key => $label) {
                        $labels = array_map(static fn (string $id): string => ($choices[$key][$id] ?? $id).' ['.$id.']', $identifiers[$key]);
                        $this->line($label.': '.($labels === [] ? 'none' : implode(', ', $labels)));
                    }
                    if ($modules !== 'all') {
                        $dependencies = array_diff($identifiers['modules'], array_map('trim', explode(',', $modules)));
                        if ($dependencies !== []) {
                            $this->line('Required modules added: '.implode(', ', $dependencies));
                        }
                    }
                    $this->line('Source changes ('.count($changes).'):');
                    foreach ($changes as $change) {
                        $this->line('  '.$change['action'].' '.$change['path']);
                    }
                    $this->line('Source selection does not install or activate extensions. Demo and quick login remain disabled for new projects.');
                    if ($this->option('local')) {
                        $localPlan = $environment->plan($root, $profile);
                        $this->showLocal($localPlan);
                    }
                    $this->confirmPlan($cancelled);
                });
            }
            if ($localPlan !== null) {
                $environment->apply($localPlan, $profile);
            }
            $this->info('Project '.$profile->name.' prepared; upstream '.$profile->coreVersion.' at '.$profile->coreSha);
            if ($localPlan !== null) {
                $this->line('Local configuration is ready. The host launcher will start Sail and print the actual /install URL.');
            } else {
                $this->line('Sources are ready. Run make init on the Linux host to prepare and start local development.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($cancelled) {
                $this->warn('Initialization cancelled. Project sources and environment were not changed.');

                return 130;
            }
            $this->error($exception->getMessage());
            $this->line('Resolve the reported step and run make init again. An initialized project keeps its recorded version and composition.');

            return self::FAILURE;
        } finally {
            if ($session !== null) {
                $sources->cleanup($session);
            }
        }
    }

    /**
     * @param  array{brands:array<string,string>,themes:array<string,string>,modules:array<string,string>,block_packages:array<string,string>}  $choices
     * @return array{string,string,string,string,string,string}
     */
    private function selections(array $choices, string $root): array
    {
        $name = (string) ($this->argument('name') ?? $this->ask('Project name', $this->suggestedName($root)));
        $brand = (string) ($this->option('brand') ?? $this->chooseOne('Administrative brand', $choices['brands'], 'dbcms'));
        $themes = (string) ($this->option('theme') ?? $this->chooseOne('Public theme', $choices['themes'], 'db-cyber'));
        $deployment = (string) ($this->option('deploy') ?? $this->chooseOne('Deployment tools to retain (choose explicitly)', [
            'github-actions' => 'GitHub Actions — workflows and deploy-php tooling',
            'ansistrano' => 'Ansistrano — Ansible deployment playbooks',
            'rsync' => 'Rsync — deployment script and shared runbook',
            'docker' => 'Docker — production image and Compose deployment',
            'none' => 'None — local Sail only; remove production deployment tools',
        ]));
        $modules = (string) $this->option('modules');
        $packages = (string) $this->option('block-packages');
        if ($this->input->isInteractive() && ! $this->input->hasParameterOption(['--modules', '--block-packages']) && $this->confirm('Customize modules and block packages?', false)) {
            $modules = $this->chooseMany('Module sources', $choices['modules']);
            $packages = $this->chooseMany('Block package sources', $choices['block_packages']);
        }

        return [$name, $brand, $themes, $deployment, $modules, $packages];
    }

    private function suggestedName(string $root): string
    {
        $directory = getenv('DBCMS_PROJECT_DIRECTORY') ?: $root;
        $name = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(basename($directory))) ?? '', '-');

        return preg_match('/^[a-z]/', $name) ? $name : 'web'.($name === '' ? '' : '-'.$name);
    }

    /** @param array<string,string> $choices */
    private function chooseOne(string $question, array $choices, ?string $default = null): string
    {
        if ($choices === []) {
            throw new DomainException('No available choices for '.$question.'.');
        }
        $ids = array_keys($choices);
        $labels = array_map(static fn (string $id, string $label): string => $label.' ['.$id.']', $ids, array_values($choices));
        $defaultIndex = $default === null ? null : array_search($default, $ids, true);
        $answer = $this->choice($question, $labels, $defaultIndex === false ? null : $defaultIndex);
        $index = array_search($answer, $labels, true);

        return $index !== false ? $ids[$index] : throw new DomainException('Select an available choice.');
    }

    /** @param array<string,string> $choices */
    private function chooseMany(string $question, array $choices): string
    {
        if ($choices === []) {
            return 'none';
        }
        $ids = array_keys($choices);
        $labels = ['All available sources', 'No optional sources', ...array_map(static fn (string $id, string $label): string => $label.' ['.$id.']', $ids, array_values($choices))];
        $answers = $this->choice($question.' (comma-separated choices)', $labels, 0, null, true);
        if (! is_array($answers)) {
            throw new DomainException('Select sources from the displayed catalogue.');
        }
        if (in_array($labels[0], $answers, true) || in_array($labels[1], $answers, true)) {
            if (count($answers) !== 1) {
                throw new DomainException('Choose all, none, or individual sources separately.');
            }

            return $answers[0] === $labels[0] ? 'all' : 'none';
        }
        $selected = [];
        foreach ($answers as $answer) {
            $index = array_search($answer, $labels, true);
            if ($index === false || $index < 2) {
                throw new DomainException('Select sources from the displayed catalogue.');
            }
            $selected[] = $ids[$index - 2];
        }

        return implode(',', $selected);
    }

    private function confirmPlan(bool &$cancelled): void
    {
        if ($this->input->isInteractive() && ! $this->confirm('Apply this plan?', false)) {
            $cancelled = true;
            throw new DomainException('Cancelled.');
        }
    }

    private function showProfile(ProjectProfile $profile): void
    {
        $this->newLine();
        $this->line('Stable release: '.$profile->coreVersion.'; SHA: '.$profile->coreSha);
        $this->line('Project: '.$profile->name.'; brand: '.$profile->brand.'; public themes: '.implode(', ', $profile->themes).'; default: '.$profile->defaultTheme);
        $this->line('Deployment: '.$profile->deployment);
    }

    private function showLocal(ProjectLocalEnvironmentPlan $plan): void
    {
        $this->line($plan->createsEnvironment ? 'Create .env with demo and quick login disabled.' : 'Preserve the existing .env and keys; generate APP_KEY only if missing.');
        $this->line('Compose project: '.$plan->projectId);
        foreach ($plan->ports as $name => $port) {
            $this->line('  '.$name.'='.$port);
        }
        foreach ($plan->urls as $label => $url) {
            $this->line('  '.$label.': '.$url.' (planned)');
        }
    }

    /** @param array<string,string> $availableThemes */
    private function assertRecordedArguments(ProjectProfile $profile, string $root, array $availableThemes): void
    {
        foreach (['brand' => $profile->brand, 'deploy' => $profile->deployment] as $option => $stored) {
            if ($this->option($option) !== null && $this->option($option) !== $stored) {
                throw new DomainException('Project already initialized with a different '.$option.'. Use the deliberate project update workflow to change composition.');
            }
        }
        if ($this->argument('name') !== null && $this->argument('name') !== $profile->name) {
            throw new DomainException('Project already initialized with a different name.');
        }
        if ($this->option('theme') !== null) {
            $themes = $this->option('theme') === 'all' ? array_keys($availableThemes) : array_values(array_unique(array_map('trim', explode(',', (string) $this->option('theme')))));
            $stored = $profile->themes;
            sort($themes);
            sort($stored);
            if ($themes !== $stored) {
                throw new DomainException('Project already initialized with different public themes.');
            }
        }
        if ($this->input->hasParameterOption(['--modules', '--block-packages'])) {
            $resolved = (new ProjectSourceSelection(new Filesystem, $root, $profile->coreVersion))->resolve((string) $this->option('modules'), (string) $this->option('block-packages'));
            foreach (['module_directories', 'block_package_directories'] as $key) {
                if ($resolved[$key] !== ($profile->sourceSelection[$key] ?? null)) {
                    throw new DomainException('Project already initialized with different extension sources.');
                }
            }
        }
    }
}
