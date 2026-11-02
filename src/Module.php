<?php

namespace RCV\Core;

use Illuminate\Support\Facades\Log;
use RCV\Core\Contracts\ModuleInterface;
use RCV\Core\Install\BaseInstaller;
use RCV\Core\Models\ModuleState;
use RCV\Core\Services\ModuleManager;
use RCV\Core\Services\ModuleNameValidator;

abstract class Module implements ModuleInterface
{
    protected $name;

    protected $version;

    protected $description;

    protected $enabled = false;

    protected $path;

    protected $installer;

    public function __construct()
    {
        $this->name = $this->getName();
        $this->version = $this->getVersion();
        $this->description = $this->getDescription();
        $this->path = $this->getPath();
        $this->installer = $this->getInstaller();

        try {
            $this->enabled = app(ModuleManager::class)->isModuleEnabled($this->name);
        } catch (\Throwable $exception) {
            Log::debug("Unable to load cached state for module [{$this->name}].", ['exception' => $exception]);
        }
    }

    abstract public function getName(): string;

    abstract public function getVersion(): string;

    abstract public function getDescription(): string;

    abstract protected function getInstaller(): BaseInstaller;

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): bool
    {
        if ($this->enabled) {
            return true;
        }

        $completedSteps = [];
        $failedStep = 'recording_install_state';

        try {
            $this->persistModuleState(false, 'installing');

            $failedStep = 'installer';
            $this->installer->install();
            $completedSteps[] = 'installer';

            $failedStep = 'activating_module';
            $this->persistModuleState(true, 'enabled', 'last_enabled_at');

            $this->enabled = true;
            app(ModuleManager::class)->refreshEnabledModulesCache();

            return true;
        } catch (\Throwable $e) {
            $this->enabled = false;
            $this->recordLifecycleFailure('enable', $failedStep, $completedSteps, $e, false);

            return false;
        }
    }

    public function disable(): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $completedSteps = [];
        $failedStep = 'recording_uninstall_state';

        try {
            $this->persistModuleState(true, 'disabling');

            $failedStep = 'uninstaller';
            $this->installer->uninstall();
            $completedSteps[] = 'uninstaller';

            $failedStep = 'deactivating_module';
            $this->persistModuleState(false, 'disabled', 'last_disabled_at');

            $this->enabled = false;
            app(ModuleManager::class)->refreshEnabledModulesCache();

            return true;
        } catch (\Throwable $e) {
            $this->enabled = true;
            $this->recordLifecycleFailure('disable', $failedStep, $completedSteps, $e, true);

            return false;
        }
    }

    public function getPath(): string
    {
        if (! ModuleNameValidator::isValid($this->getModuleNameLower())) {
            throw new \InvalidArgumentException('Invalid module name.');
        }

        return module_path($this->getModuleNameLower());
    }

    protected function getModuleNameLower(): string
    {
        // Convert camel case to kebab case
        $name = preg_replace('/(?<!^)[A-Z]/', '-$0', $this->name);

        // Convert to lowercase
        return strtolower($name);
    }

    protected function getModuleNameStudly(): string
    {
        // Convert kebab case to studly case
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->getModuleNameLower())));
    }

    protected function persistModuleState(bool $enabled, string $status, ?string $timestampColumn = null): void
    {
        $state = ModuleState::firstOrNew(['name' => $this->name]);
        $state->version = $this->version;
        $state->description = $this->description;
        $state->enabled = $enabled;
        $state->status = $status;
        if ($timestampColumn !== null) {
            $state->{$timestampColumn} = now();
        }
        $state->save();
    }

    protected function recordLifecycleFailure(
        string $operation,
        string $failedStep,
        array $completedSteps,
        \Throwable $exception,
        bool $enabled
    ): void {
        Log::error("Module {$operation} failed.", [
            'module' => $this->name,
            'failed_step' => $failedStep,
            'completed_steps' => $completedSteps,
            'exception' => $exception,
        ]);
        report($exception);

        try {
            $status = $operation === 'enable' ? 'enable_failed' : 'disable_failed';
            $this->persistModuleState($enabled, $status);
            app(ModuleManager::class)->refreshEnabledModulesCache();
        } catch (\Throwable $recoveryException) {
            Log::critical("Unable to record failed module {$operation} state.", [
                'module' => $this->name,
                'failed_step' => $failedStep,
                'exception' => $recoveryException,
            ]);
            report($recoveryException);
        }
    }
}
