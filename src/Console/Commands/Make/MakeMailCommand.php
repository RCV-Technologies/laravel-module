<?php

namespace Rcv\Core\Console\Commands\Make;


use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeMailCommand extends Command
{
    protected $signature = 'module:make-mail
                            {module : The name of the module}
                            {name : The name of the mail class}';

    protected $description = 'Create a new email class for the specified module';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

   public function handle()
{
    $module = $this->argument('module');
    $name = Str::studly($this->argument('name'));

    // Updated path for your requirement
    $mailPath = base_path("Modules/{$module}/src/User/Mails");

    if (! $this->files->isDirectory($mailPath)) {
        $this->files->makeDirectory($mailPath, 0755, true);
    }

    $classFile = $mailPath . '/' . $name . '.php';

    if ($this->files->exists($classFile)) {
        $this->error("Mail class {$name} already exists in module {$module}!");
        return 1;
    }

    $stub = $this->getStub();

    // Update namespace accordingly
    $stub = str_replace(
        ['{{namespace}}', '{{class}}'],
        ["Modules\\{$module}\\User\\Mails", $name],
        $stub
    );

    $this->files->put($classFile, $stub);

    $this->info("Mail class {$name} created successfully in module {$module}!");
    $this->info("Path: {$classFile}");

    return 0;
}

    protected function getStub()
    {
        // Adjust path according to where you store your stubs
        return $this->files->get(__DIR__ . '/../stubs/mail.stub');
    }
}
