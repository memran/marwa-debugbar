<?php

declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Contracts\CollectorException;
use Marwa\DebugBar\Core\DebugState;
use ReflectionClass;
use Throwable;

final class CollectorManager
{
    /** @var array<string,class-string<Collector>> */
    private array $classes = [];

    /** @var array<string,bool> */
    private array $enabled = [];

    /**
     * @param class-string<Collector> $collectorClass
     */
    public function register(string $collectorClass, bool $enabled = true): void
    {
        if (!is_subclass_of($collectorClass, Collector::class)) {
            throw new CollectorException(sprintf('%s must implement %s', $collectorClass, Collector::class));
        }

        $key = $collectorClass::key();
        if (isset($this->classes[$key])) {
            throw new CollectorException(sprintf(
                "Collector key '%s' is already registered by %s",
                $key,
                $this->classes[$key]
            ));
        }

        $this->classes[$key] = $collectorClass;
        $this->enabled[$key] = $enabled;
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        if (!isset($this->classes[$key])) {
            throw new CollectorException(sprintf("Collector '%s' not found", $key));
        }

        $this->enabled[$key] = $enabled;
    }

    public function autoDiscover(string $directory, string $baseNamespace): int
    {
        $count = 0;
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($directory)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            require_once $path;

            $relative = ltrim(str_replace([$directory, DIRECTORY_SEPARATOR], ['', '\\'], $path), '\\');
            $relative = (string) preg_replace('/\.php$/', '', $relative);
            $class = rtrim($baseNamespace, '\\') . '\\' . $relative;

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (!$reflection->isInstantiable() || !$reflection->implementsInterface(Collector::class)) {
                continue;
            }

            if (isset($this->classes[$class::key()])) {
                continue;
            }

            /** @var class-string<Collector> $class */
            $this->register($class);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{key:string,label:string,icon:string,order:int,html:string,data:array<string,mixed>}>
     */
    public function renderAll(DebugState $state): array
    {
        $rows = [];

        foreach ($this->classes as $key => $class) {
            if (!($this->enabled[$key] ?? false)) {
                continue;
            }

            $label = $class::label();
            $icon = $class::icon();
            $order = $class::order();

            try {
                $instance = new $class();
                $data = $instance->collect($state);
                $html = $instance->renderHtml($data);
            } catch (Throwable $exception) {
                $data = [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ];
                $html = sprintf(
                    '<div class="mw-card"><div class="mw-card-h">%s failed</div><div class="mw-card-b"><pre class="mw-pre">%s</pre></div></div>',
                    htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($exception::class . ': ' . $exception->getMessage(), ENT_QUOTES, 'UTF-8')
                );
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'order' => $order,
                'html' => $html,
                'data' => $data,
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $left['order'] <=> $right['order']);

        return $rows;
    }

    /**
     * @return list<array{key:string,label:string,icon:string,order:int}>
     */
    public function metadata(): array
    {
        $metadata = [];

        foreach ($this->classes as $key => $class) {
            if (!($this->enabled[$key] ?? false)) {
                continue;
            }

            $metadata[] = [
                'key' => $key,
                'label' => $class::label(),
                'icon' => $class::icon(),
                'order' => $class::order(),
            ];
        }

        usort($metadata, static fn(array $left, array $right): int => $left['order'] <=> $right['order']);

        return $metadata;
    }
}
