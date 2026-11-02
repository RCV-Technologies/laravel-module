<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMigrationMakeCommand extends Command
{
    protected $signature = 'module:make-migration
        {name : The migration name (e.g. create_posts_table, add_slug_to_posts_table)}
        {module : The module to place the migration in}
        {--fields= : The fields for the migration, e.g. title:string,slug:text}
        {--plain : Generate a plain migration without fields}';

    protected $description = 'Create a new migration file in a specific module';

    protected function getStubPath()
    {
         return __DIR__ . '/../../stubs/migrations';
    }

    protected function getStub()
    {
        $name = $this->argument('name');

        if (Str::startsWith($name, 'create_') && Str::endsWith($name, '_table')) {
            return $this->getStubPath() . '/create.stub';
        }

        if (Str::startsWith($name, 'add_')) {
            return $this->getStubPath() . '/add.stub';
        }

        if (Str::startsWith($name, 'delete_')) {
            return $this->getStubPath() . '/delete.stub';
        }

        if (Str::startsWith($name, 'drop_')) {
            return $this->getStubPath() . '/drop.stub';
        }

        return $this->getStubPath() . '/plain.stub';
    }

    protected function getTableName()
    {
        $name = $this->argument('name');

        // create_*_table
        if (Str::startsWith($name, 'create_') && Str::endsWith($name, '_table')) {
            return Str::replaceFirst('create_', '', Str::replaceLast('_table', '', $name));
        }

        // drop_*_table
        if (Str::startsWith($name, 'drop_') && Str::endsWith($name, '_table')) {
            return Str::replaceFirst('drop_', '', Str::replaceLast('_table', '', $name));
        }

        // delete_*_table
        if (Str::startsWith($name, 'delete_') && Str::contains($name, '_from_') && Str::endsWith($name, '_table')) {
            return Str::between($name, '_from_', '_table');
        }


        // add_*_to_*_table / delete_*_from_*_table
        if (preg_match('/_(?:to|from)_(.+)_table$/', $name, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function getMigrationType()
    {
        $name = $this->argument('name');

        if (Str::startsWith($name, 'create_') && Str::endsWith($name, '_table')) {
            return 'create';
        }

        if (Str::startsWith($name, 'add_')) {
            return 'add';
        }

        if (Str::startsWith($name, 'delete_')) {
            return 'delete';
        }

        if (Str::startsWith($name, 'drop_')) {
            return 'drop';
        }

        return 'plain';
    }

    protected function parseFields()
    {
        $fieldsUp = '';
        $fieldsDown = '';

        if ($this->option('plain')) {
            return [$fieldsUp, $fieldsDown];
        }

        $type = $this->getMigrationType();

        if ($fields = $this->option('fields')) {
            $fieldsArr = explode(',', $fields);
            foreach ($fieldsArr as $field) {
                $parts = explode(':', $field);
                $name = $parts[0] ?? null;
                $colType = $parts[1] ?? 'string';

                if (!$name) continue;

                switch ($type) {
                    case 'add':
                    case 'create':
                        $fieldsUp   .= "            \$table->{$colType}('{$name}');\n";
                        $fieldsDown .= "            \$table->dropColumn('{$name}');\n";
                        break;

                    case 'delete':
                        $fieldsUp   .= "            \$table->dropColumn('{$name}');\n";
                        $fieldsDown .= "            \$table->{$colType}('{$name}');\n";
                        break;

                    // for drop.stub we usually don’t need fields
                    default:
                        break;
                }
            }
        }

        return [$fieldsUp, $fieldsDown];
    }


    protected function getTemplateContents()
    {
        $stubPath = $this->getStub();

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            exit(1);
        }

        $class = $this->getClassName();
        $table = $this->getTableName();

        list($fieldsUp, $fieldsDown) = $this->parseFields();

        $stub = file_get_contents($stubPath);

        $stub = str_replace(
            ['{{ class }}', '{{ table }}', '{{ fields_up }}', '{{ fields_down }}'],
            [$class, $table ?? '', trim($fieldsUp), trim($fieldsDown)],
            $stub
        );

        return $stub;
    }

    protected function getClassName()
    {
        return Str::studly($this->argument('name'));
    }

    protected function getDestinationFilePath()
    {
        $module = $this->argument('module');
        $path = module_path($module, 'src/Database/Migrations/');
        return $path . $this->getFileName() . '.php';
    }

    protected function getFileName()
    {
        return date('Y_m_d_His_') . Str::snake($this->argument('name'));
    }

    public function handle()
    {
        $this->info('Creating migration...');

        $path = $this->getDestinationFilePath();
        $contents = $this->getTemplateContents();

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);

        $this->info("Migration created: {$path}");

        return 0;
    }
}
