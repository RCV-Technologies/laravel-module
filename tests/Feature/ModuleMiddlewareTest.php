<?php

namespace RCV\Core\Tests\Feature;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RCV\Core\Http\Middleware\ModuleMiddleware;
use RCV\Core\Services\ModuleManager;
use RCV\Core\Tests\TestCase;
use Throwable;

class ModuleMiddlewareTest extends TestCase
{
    public function test_validation_exception_is_rethrown_to_the_application_handler(): void
    {
        $moduleManager = $this->createMock(ModuleManager::class);
        $moduleManager->expects($this->once())
            ->method('isModuleEnabled')
            ->with('Example')
            ->willReturn(true);
        $this->app->instance(ModuleManager::class, $moduleManager);

        $exception = ValidationException::withMessages(['name' => 'The name field is required.']);

        try {
            (new TestModuleMiddleware)->handle(
                Request::create('/'),
                static function () use ($exception): never {
                    throw $exception;
                }
            );

            $this->fail('The validation exception should be rethrown.');
        } catch (ValidationException $caughtException) {
            $this->assertSame($exception, $caughtException);
        }
    }

    public function test_package_error_views_render_using_the_shared_error_layout(): void
    {
        $disabledView = view('core::errors.module-disabled', ['module' => 'Example'])->render();
        $errorView = view('core::errors.module-error', [
            'module' => 'Example',
            'error' => 'An unexpected error occurred.',
        ])->render();

        $this->assertStringContainsString('<html', $disabledView);
        $this->assertStringContainsString('Module Disabled', $disabledView);
        $this->assertStringContainsString('<html', $errorView);
        $this->assertStringContainsString('Module Error', $errorView);
    }
}

class TestModuleMiddleware extends ModuleMiddleware
{
    protected function processRequest(Request $request, Closure $next)
    {
        return $next($request);
    }

    protected function getModuleName(): string
    {
        return 'Example';
    }

    protected function logError(Request $request, Throwable $e): void
    {
        // Keep the test focused on exception propagation.
    }
}
