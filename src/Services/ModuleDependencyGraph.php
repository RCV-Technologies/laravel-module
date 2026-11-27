<?php

namespace RCV\Core\Services;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RCV\Core\Services\MarketplaceService;

/**
 * ModuleDependencyGraph
 *
 * Builds a graph of modules and their inter-dependencies and provides helpers
 * for serialization (JSON/DOT) and detecting issues (missing/disabled deps,
 * circular dependencies).
 */
class ModuleDependencyGraph
{
    /**
     * Cache key for the generated dependency graph.
     */
    public const CACHE_KEY = 'modules.dependency_graph.v1';

    protected string $modulePath;
    protected ?CacheManager $cacheManager;
    protected MarketplaceService $marketplaceService;
    protected bool $cacheEnabled;
    protected int $cacheTtl;

    public function __construct(?CacheManager $cacheManager, MarketplaceService $marketplaceService)
    {
        $this->modulePath = base_path('Modules');
        $this->cacheManager = $cacheManager;
        $this->marketplaceService = $marketplaceService;

        // Prefer explicit config keys for module graph caching (fallback to false)
        $this->cacheEnabled = Config::get('modules.dependency_graph.cache_enabled', false);
        $this->cacheTtl = (int) Config::get('modules.dependency_graph.cache_ttl', 60);
    }

    /**
     * Generate the graph structure (nodes + edges). Result cached if configured.
     *
     * @return array{nodes: array, edges: array}
     */
    public function generateGraph(): array
    {
        if ($this->cacheManager && $this->cacheEnabled) {
            return $this->cacheManager->remember(self::CACHE_KEY, $this->cacheTtl, fn() => $this->buildGraph());
        }

        return $this->buildGraph();
    }

    /**
     * Return nodes array
     *
     * @return array<int,array{name:string,enabled:bool,version:string}>
     */
    public function getNodes(): array
    {
        return $this->generateGraph()['nodes'];
    }

    /**
     * Return edges array
     *
     * @return array<int,array{from:string,to:string,type:string}>
     */
    public function getEdges(): array
    {
        return $this->generateGraph()['edges'];
    }

    /**
     * Convenience to get all issues (dependency problems + circular dependencies)
     *
     * @return array
     */
    public function detectIssues(): array
    {
        $issues = [];
        $issues = array_merge($issues, $this->validateDependencies());
        $circular = $this->findCircularDependencies();
        if (!empty($circular)) {
            foreach ($circular as $cycle) {
                $issues[] = 'Circular dependency detected: ' . implode(' -> ', $cycle);
            }
        }
        return $issues;
    }

    /**
     * Generate DOT representation suitable for Graphviz.
     */
    public function generateDotGraph(): string
    {
        $graph = $this->generateGraph();
        $dot = "digraph ModuleDependencies {\n";
        $dot .= "    rankdir=LR;\n";
        $dot .= "    node [shape=box, style=filled, color=black];\n\n";

        // Add nodes
        foreach ($graph['nodes'] as $node) {
            $color = $node['enabled'] ? 'lightgreen' : 'lightpink';
            $dot .= sprintf("    \"%s\" [fillcolor=\"%s\"];\n", $node['name'], $color);
        }

        $dot .= "\n";

        // Add edges
        foreach ($graph['edges'] as $edge) {
            $dot .= sprintf("    \"%s\" -> \"%s\";\n", $edge['from'], $edge['to']);
        }

        $dot .= "}\n";

        return $dot;
    }

    /**
     * JSON representation of the graph
     */
    public function generateJsonGraph(): string
    {
        return json_encode($this->generateGraph(), JSON_PRETTY_PRINT);
    }

    /**
     * Build graph from available modules + composer requires that reference Modules/
     *
     * @return array{nodes: array, edges: array}
     */
    protected function buildGraph(): array
    {
        $nodes = [];
        $edges = [];

        // Attempt to get available modules from marketplace service (expects array)
        try {
            $modules = $this->marketplaceService->list();
        } catch (\Throwable $e) {
            Log::error('Failed to list modules from marketplace service: ' . $e->getMessage());
            $modules = [];
        }

        $moduleManager = app(ModuleManager::class);

        foreach ($modules as $module) {
            $name = $module['name'] ?? ($module['module'] ?? null);
            if (!$name) {
                continue;
            }

            $nodes[] = [
                'name' => $name,
                'enabled' => $moduleManager->isModuleEnabled($name),
                'version' => $module['version'] ?? '1.0.0',
            ];
        }

        // For edges, read composer.json for each module and pick require entries like "Modules/Other"
        foreach ($nodes as $node) {
            $moduleName = $node['name'];
            $dependencies = $this->getModuleDependencies($moduleName);
            foreach ($dependencies as $dep) {
                $edges[] = [
                    'from' => $moduleName,
                    'to' => $dep,
                    'type' => 'requires',
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Read module composer.json and return module dependencies referencing Modules/...
     *
     * @return string[] list of module names this module requires
     */
    protected function getModuleDependencies(string $moduleName): array
    {
        $dependencies = [];
        $composerFile = "{$this->modulePath}/{$moduleName}/composer.json";

        if (!File::exists($composerFile)) {
            return $dependencies;
        }

        $raw = File::get($composerFile);
        $composer = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($composer)) {
            Log::warning("Invalid composer.json for module {$moduleName}");
            return $dependencies;
        }

        if (!empty($composer['require']) && is_array($composer['require'])) {
            foreach ($composer['require'] as $package => $ver) {
                if (is_string($package) && str_starts_with($package, 'Modules/')) {
                    $dependencies[] = str_replace('Modules/', '', $package);
                }
            }
        }

        return $dependencies;
    }

    /**
     * Validate dependencies: ensure required modules exist and are enabled
     *
     * @return array list of human-readable issues
     */
    public function validateDependencies(): array
    {
        $issues = [];
        $graph = $this->generateGraph();
        $nodesIndex = [];
        foreach ($graph['nodes'] as $n) {
            $nodesIndex[$n['name']] = $n;
        }

        foreach ($graph['edges'] as $edge) {
            $from = $edge['from'];
            $to = $edge['to'];

            if (!isset($nodesIndex[$to])) {
                $issues[] = "Module [{$from}] requires [{$to}] which is not installed";
                continue;
            }

            if (!$nodesIndex[$to]['enabled']) {
                $issues[] = "Module [{$from}] requires [{$to}] which is currently disabled";
            }
        }

        return $issues;
    }

    /**
     * Detect circular dependencies using DFS and return array of cycles (each cycle is array of module names)
     *
     * @return array<int,array<int,string>>
     */
    public function findCircularDependencies(): array
    {
        $graph = $this->generateGraph();
        $adj = [];

        foreach ($graph['nodes'] as $n) {
            $adj[$n['name']] = [];
        }
        foreach ($graph['edges'] as $e) {
            $adj[$e['from']][] = $e['to'];
        }

        $visited = [];
        $stack = [];
        $cycles = [];

        foreach (array_keys($adj) as $node) {
            if (!isset($visited[$node])) {
                $this->dfsDetectCycles($node, $adj, $visited, $stack, $cycles);
            }
        }

        return $cycles;
    }

    /**
     * DFS helper to detect cycles. Maintains a path stack of nodes currently in recursion.
     */
    protected function dfsDetectCycles(string $node, array $adj, array &$visited, array &$stack, array &$cycles): void
    {
        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($adj[$node] ?? [] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $this->dfsDetectCycles($neighbor, $adj, $visited, $stack, $cycles);
            } elseif (isset($stack[$neighbor]) && $stack[$neighbor] === true) {
                // Found a back-edge to neighbor which is in the current recursion stack -> build cycle
                $cycle = $this->extractCycleFromStack($stack, $neighbor);
                if ($cycle && !in_array($cycle, $cycles, true)) {
                    $cycles[] = $cycle;
                }
            }
        }

        // remove from current recursion stack
        $stack[$node] = false;
    }

    /**
     * Extract cycle starting at $start using keys in $stack where value === true
     *
     * @param array<string,bool> $stack
     * @param string $start
     * @return string[] the cycle nodes in order
     */
    protected function extractCycleFromStack(array $stack, string $start): array
    {
        // Keep only nodes that are currently true in stack in insertion order
        $active = array_keys(array_filter($stack, fn($v) => $v === true));

        $startIndex = array_search($start, $active, true);
        if ($startIndex === false) {
            return [];
        }

        // slice from startIndex to end, and append start to show cycle closure
        $cycle = array_slice($active, $startIndex);
        $cycle[] = $start;

        return $cycle;
    }
}
