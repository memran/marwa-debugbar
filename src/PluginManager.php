<?php

declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Contracts\Plugin;

final class PluginManager
{
    /** @var array<string,Plugin> */
    private array $plugins = [];

    public function register(Plugin $plugin, bool $enabled = true): void
    {
        $name = $plugin->name();
        if (isset($this->plugins[$name])) {
            throw new \InvalidArgumentException("Plugin '$name' already registered.");
        }
        $plugin->setEnabled($enabled);
        $this->plugins[$name] = $plugin;
        $plugin->boot();
    }

    public function enable(string $name): void
    {
        $this->get($name)->setEnabled(true);
    }
    public function disable(string $name): void
    {
        $this->get($name)->setEnabled(false);
    }

    public function get(string $name): Plugin
    {
        if (!isset($this->plugins[$name])) throw new \InvalidArgumentException("Plugin '$name' not found.");
        return $this->plugins[$name];
    }

    public function extendPayload(array $payload): array
    {
        foreach ($this->plugins as $p) {
            if ($p->isEnabled()) $payload = array_merge($payload, $p->extendPayload($payload));
        }
        return $payload;
    }

    /** @return array<int,array{key:string,title:string,icon?:string,order?:int,renderer:string}> */
    public function tabs(): array
    {
        $tabs = [];
        foreach ($this->plugins as $p) {
            if (!$p->isEnabled()) continue;
            foreach ($p->tabs() as $tab) {
                $tab['order'] = $tab['order'] ?? 500;
                $tabs[] = $tab;
            }
        }
        usort($tabs, fn($a, $b) => ($a['order'] <=> $b['order']));
        return $tabs;
    }
}
