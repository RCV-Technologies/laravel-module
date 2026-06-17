<?php

namespace RCV\Core\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RCV\Core\Hooks\Facades\Hook;
use RCV\Core\Hooks\HookManager;
use RCV\Core\Hooks\HookRegistry;
use RCV\Core\Hooks\Contracts\HookListenerInterface;
use RCV\Core\Hooks\Discovery\ListenerDiscoverer;
use RCV\Core\Services\ModuleManager;

class HookSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Enable hooks by default for testing
        Config::set('rcv-hooks.enabled', true);
        Config::set('rcv-hooks.failure_policy', 'continue');
    }

    protected function tearDown(): void
    {
        // Clean up config file if created
        if (File::exists(config_path('rcv-hooks.php'))) {
            File::delete(config_path('rcv-hooks.php'));
        }

        // Clean up test directories
        if (File::isDirectory(base_path('Modules'))) {
            File::deleteDirectory(base_path('Modules'));
        }

        parent::tearDown();
    }

    /**
     * Test basic hook registration and execution.
     */
    public function test_hook_registration_and_execution()
    {
        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $executed = false;
        $manager->listen('test.hook', function () use (&$executed) {
            $executed = true;
        });

        $manager->execute('test.hook');

        $this->assertTrue($executed);
    }

    /**
     * Test hook execution with arguments.
     */
    public function test_hook_execution_with_arguments()
    {
        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $passedArg1 = null;
        $passedArg2 = null;

        $manager->listen('test.hook.args', function ($arg1, $arg2) use (&$passedArg1, &$passedArg2) {
            $passedArg1 = $arg1;
            $passedArg2 = $arg2;
        });

        $manager->execute('test.hook.args', 'hello', 'world');

        $this->assertEquals('hello', $passedArg1);
        $this->assertEquals('world', $passedArg2);
    }

    /**
     * Test priority order of hook execution.
     */
    public function test_priority_ordering()
    {
        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $order = [];

        $manager->listen('test.priority', function () use (&$order) {
            $order[] = 'second';
        }, 15);

        $manager->listen('test.priority', function () use (&$order) {
            $order[] = 'first';
        }, 5);

        $manager->listen('test.priority', function () use (&$order) {
            $order[] = 'third';
        }, 20);

        $manager->execute('test.priority');

        $this->assertEquals(['first', 'second', 'third'], $order);
    }

    /**
     * Test filter hooks transformation chain.
     */
    public function test_filter_application()
    {
        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $manager->filter('test.filter', function ($value) {
            return $value . ' world';
        }, 10);

        $manager->filter('test.filter', function ($value) {
            return strtoupper($value);
        }, 20);

        $result = $manager->apply('test.filter', 'hello');

        $this->assertEquals('HELLO WORLD', $result);
    }

    /**
     * Test rendering behavior (output hook rendering).
     */
    public function test_rendering_behavior()
    {
        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $manager->listen('test.render', function () {
            return '<div>Widget A</div>';
        }, 10);

        $manager->listen('test.render', function () {
            return '<div>Widget B</div>';
        }, 20);

        // This one returns an array/non-string and should be ignored during concatenation
        $manager->listen('test.render', function () {
            return ['not a string'];
        }, 30);

        $result = $manager->render('test.render');

        $this->assertEquals('<div>Widget A</div><div>Widget B</div>', $result);
    }

    /**
     * Test hook system when disabled in config.
     */
    public function test_disabled_mode_behavior()
    {
        Config::set('rcv-hooks.enabled', false);

        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $executed = false;
        $manager->listen('test.disabled', function () use (&$executed) {
            $executed = true;
        });

        $manager->execute('test.disabled');
        $this->assertFalse($executed);

        // Filter should return input unmodified
        $manager->filter('test.disabled.filter', function ($value) {
            return 'modified';
        });
        $result = $manager->apply('test.disabled.filter', 'original');
        $this->assertEquals('original', $result);

        // Render should return empty string
        $manager->listen('test.disabled.render', function () {
            return 'html';
        });
        $renderResult = $manager->render('test.disabled.render');
        $this->assertEquals('', $renderResult);
    }

    /**
     * Test failure isolation with 'continue' policy.
     */
    public function test_failure_isolation_continue()
    {
        Config::set('rcv-hooks.failure_policy', 'continue');

        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $secondExecuted = false;

        $manager->listen('test.fail', function () {
            throw new \RuntimeException('Broken listener');
        }, 10);

        $manager->listen('test.fail', function () use (&$secondExecuted) {
            $secondExecuted = true;
        }, 20);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, "Hook system error in 'test.fail'") &&
                       $context['exception'] instanceof \RuntimeException;
            });

        $manager->execute('test.fail');

        $this->assertTrue($secondExecuted);
    }

    /**
     * Test failure isolation with 'fail_fast' policy.
     */
    public function test_failure_isolation_fail_fast()
    {
        Config::set('rcv-hooks.failure_policy', 'fail_fast');

        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $manager->listen('test.fail.fast', function () {
            throw new \RuntimeException('Fast crash');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fast crash');

        $manager->execute('test.fail.fast');
    }

    /**
     * Test failure isolation in filters.
     */
    public function test_failure_isolation_in_filters()
    {
        Config::set('rcv-hooks.failure_policy', 'continue');

        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $manager->filter('test.filter.fail', function ($value) {
            return $value . ' - first';
        }, 10);

        $manager->filter('test.filter.fail', function ($value) {
            throw new \RuntimeException('Filter failed');
        }, 20);

        $manager->filter('test.filter.fail', function ($value) {
            return $value . ' - third';
        }, 30);

        Log::shouldReceive('error')->once();

        $result = $manager->apply('test.filter.fail', 'base');

        // The second filter fails, so it keeps the output of the first filter and passes it to the third
        $this->assertEquals('base - first - third', $result);
    }

    /**
     * Test listener auto-discovery.
     */
    public function test_listener_discovery()
    {
        $modulesDir = base_path('Modules/Blog/src/Hooks');
        File::ensureDirectoryExists($modulesDir);

        // Write a mock listener class that implements HookListenerInterface
        $listenerClassContent = <<<PHP
<?php

namespace Modules\Blog\Hooks;

use RCV\Core\Hooks\Contracts\HookListenerInterface;

class MockPostCreatedListener implements HookListenerInterface
{
    public static function getHook(): string
    {
        return 'blog.post_created';
    }

    public static function getPriority(): int
    {
        return 15;
    }

    public function handle(...\$args)
    {
        return 'handled - ' . \$args[1];
    }
}
PHP;

        File::put($modulesDir . '/MockPostCreatedListener.php', $listenerClassContent);

        // Manually require the file to load it in PHP context for the test
        require_once $modulesDir . '/MockPostCreatedListener.php';

        // Mock the ModuleManager to return Blog as enabled module
        $mockModuleManager = $this->createMock(ModuleManager::class);
        $mockModuleManager->method('getEnabledModules')->willReturn(['Blog']);
        $this->app->instance(ModuleManager::class, $mockModuleManager);

        $registry = new HookRegistry();
        $manager = new HookManager($registry);

        $discoverer = new ListenerDiscoverer($manager, [
            'enabled' => true,
            'cache_enabled' => false, // Bypass cache for active scan testing
        ]);

        $discoverer->discover();

        $this->assertTrue($manager->exists('blog.post_created'));
        $result = $manager->apply('blog.post_created', 'value', 'test-post');
        $this->assertEquals('handled - test-post', $result);
    }

    /**
     * Test the initialization artisan command.
     */
    public function test_initialize_hooks_command()
    {
        // Verify config doesn't exist
        $this->assertFalse(File::exists(config_path('rcv-hooks.php')));

        // Mock the ModuleManager to prevent errors
        $mockModuleManager = $this->createMock(ModuleManager::class);
        $mockModuleManager->method('getEnabledModules')->willReturn([]);
        $this->app->instance(ModuleManager::class, $mockModuleManager);

        // Run the initialize-hooks command
        $exitCode = Artisan::call('module:initialize-hooks');

        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists(config_path('rcv-hooks.php')));

        $config = require config_path('rcv-hooks.php');
        $this->assertTrue($config['enabled']);
    }
}
