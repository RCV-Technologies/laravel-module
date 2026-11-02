<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeMailCommand extends Command
{
    protected $signature = 'module:make-mail
                            {name : The name of the mail class (can include subdirectories)}
                            {module : The module name}';
    protected $description = 'Create a new email class for the specified module';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $module = Str::studly($this->argument('module')); // e.g., Blog
        $nameInput = $this->argument('name');             // e.g., Welcome/UserMail

        // --- Handle subdirectories ---
        $parts = preg_split('/[\/\\\\]+/', $nameInput);
        $className = Str::studly(array_pop($parts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $parts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $parts));

        // --- Paths & Namespace ---
        $moduleBasePath = module_path($module);
        if (! $this->files->exists($moduleBasePath)) {
            $this->error("Module '{$module}' does not exist.");
            return Command::FAILURE;
        }

        $mailPath = $moduleBasePath . "/src/Mails" . ($subPath ? "/{$subPath}" : '');
        $filePath = "{$mailPath}/{$className}.php";
        $namespace = "Modules\\{$module}\\Mails" . ($subNamespace ? "\\{$subNamespace}" : '');

        if ($this->files->exists($filePath)) {
            $this->error("Mail class {$className} already exists in module {$module}!");
            return Command::FAILURE;
        }

        $this->files->ensureDirectoryExists($mailPath);

        $stub = $this->getStub();
        $stub = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        $this->files->put($filePath, $stub);

        $this->info("Mail class '{$className}' created successfully in module '{$module}'!");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }

    protected function getStub()
    {
        return $this->files->get(__DIR__ . '/../stubs/mail.stub');
    }
}
