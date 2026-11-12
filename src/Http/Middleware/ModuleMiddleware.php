<?php

namespace RCV\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RCV\Core\Services\ModuleManager;

abstract class ModuleMiddleware
{
    /**
     * The module name.
     *
     * @var string
     */
    protected $moduleName;

    /**
     * Create a new middleware instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->moduleName = $this->getModuleName();
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $this->isModuleEnabled()) {
            return $this->handleDisabledModule($request);
        }

        try {
            $response = $this->processRequest($request, $next);
            $this->logRequest($request, $response);

            return $response;
        } catch (\Throwable $e) {
            $this->logError($request, $e);

            throw $e;
        }
    }

    /**
     * Process the request.
     *
     * @return mixed
     */
    abstract protected function processRequest(Request $request, Closure $next);

    /**
     * Handle a disabled module.
     *
     * @return mixed
     */
    protected function handleDisabledModule(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Module is disabled',
                'module' => $this->moduleName,
            ], 403);
        }

        return response()->view('core::errors.module-disabled', [
            'module' => $this->moduleName,
        ], 403);
    }

    /**
     * Handle an error.
     *
     * @return mixed
     */
    protected function handleError(Request $request, \Throwable $e)
    {
        $message = 'An unexpected error occurred. Please try again later.';

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Module error',
                'message' => $message,
                'module' => $this->moduleName,
            ], 500);
        }

        return response()->view('core::errors.module-error', [
            'module' => $this->moduleName,
            'error' => $message,
        ], 500);
    }

    /**
     * Check if the module is enabled.
     */
    protected function isModuleEnabled(): bool
    {
        return app(ModuleManager::class)->isModuleEnabled($this->moduleName);
    }

    /**
     * Get the module name.
     */
    protected function getModuleName(): string
    {
        $class = get_class($this);
        $parts = explode('\\', $class);

        return $parts[1] ?? 'unknown';
    }

    /**
     * Log the request.
     *
     * @param  mixed  $response
     */
    protected function logRequest(Request $request, $response): void
    {
        // Request logging is intentionally omitted to avoid high-volume logs.
    }

    /**
     * Log an error.
     */
    protected function logError(Request $request, \Throwable $e): void
    {
        Log::error('Module middleware error', [
            'module' => $this->moduleName,
            'exception' => get_class($e),
        ]);
    }

    /**
     * Get the middleware's priority.
     */
    public function getPriority(): int
    {
        return config("middleware.priority.{$this->moduleName}", 0);
    }
}
