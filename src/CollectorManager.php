<?php
declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Contracts\CollectorException;
use Marwa\DebugBar\Core\DebugState;
use ReflectionClass;

/**
 * Registers collectors lazily (by class name) and orchestrates:
 *  - metadata reading without instantiation
 *  - on-demand instantiate -> collect -> renderHtml
 */
final class CollectorManager
{
    /** @var array<string,class-string<Collector>> key => FQCN */
    private array $classes = [];

    /** @var array<string,bool> */
    private array $enabled = [];

    /** Register a collector by class name (lazy instantiation). */
    public function register(string $collectorClass, bool $enabled = true): void
    {
        if (!is_subclass_of($collectorClass, Collector::class)) {
            throw new CollectorException("$collectorClass must implement Collector");
        }
        /** @var class-string<Collector> $collectorClass */
        $key = $collectorClass::key();
        if (isset($this->classes[$key])) {
            throw new CollectorException("Collector key '{$key}' already registered by {$this->classes[$key]}");
        }
        $this->classes[$key] = $collectorClass;
        $this->enabled[$key] = $enabled;
    }

    /** Enable/disable by key. */
    public function setEnabled(string $key, bool $enabled): void
    {
        if (!isset($this->classes[$key])) {
            throw new CollectorException("Collector '{$key}' not found");
        }
        $this->enabled[$key] = $enabled;
    }

    /**
     * Automatically discover collectors in a PSR-4 directory.
     * This loads PHP files to make classes known to Reflection.
     */
    public function autoDiscover(string $dir, string $baseNamespace): int
    {
        $count = 0;
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if (!is_dir($dir)) return 0;

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;

            $path = $file->getRealPath();
            if (!$path) continue;
            require_once $path; // load class

            // Derive FQCN (baseNamespace + relative path without .php)
            $rel = ltrim(str_replace([$dir, DIRECTORY_SEPARATOR], ['', '\\'], $path), '\\');
            $rel = preg_replace('/\.php$/', '', $rel);
            $class = rtrim($baseNamespace, '\\') . '\\' . $rel;

            if (!class_exists($class)) continue;

            $ref = new ReflectionClass($class);
            if ($ref->isInstantiable() && $ref->implementsInterface(Collector::class)) {
                $this->register($class);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Render every enabled collector:
     * - instantiate
     * - collect($state)
     * - renderHtml($data)
     *
     * @return array<int,array{key:string,label:string,icon:string,order:int,html:string}>
     */
    public function renderAll(DebugState $state): array
    {
        $rows = [];
        
        foreach ($this->classes as $key => $class) {
            if (!($this->enabled[$key] ?? false)) continue;

            // metadata without instantiation
            $label = $class::label();
            $icon  = $class::icon();
            $order = $class::order();

            // instantiate lazily
            /** @var Collector $instance */
            $instance = new $class();

            // collect + render
            $data = $instance->collect($state);
            $html = $instance->renderHtml($data);

            $rows[$key] = compact('key', 'label', 'icon', 'order', 'html','data');
        }

        usort($rows, fn($a,$b) => $a['order'] <=> $b['order']);
        return $rows;
    }

    /**
     * For building sidebars without instantiating collectors.
     * @return array<int,array{key:string,label:string,icon:string,order:int}>
     */
    public function metadata(): array
    {
        $meta = [];
        foreach ($this->classes as $key => $class) {
            if (!($this->enabled[$key] ?? false)) continue;
            $meta[] = [
                'key'   => $key,
                'label' => $class::label(),
                'icon'  => $class::icon(),
                'order' => $class::order(),
            ];
        }
        usort($meta, fn($a,$b) => $a['order'] <=> $b['order']);
        return $meta;
    }
}
