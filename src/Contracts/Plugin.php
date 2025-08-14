<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Contracts;

/**
 * A DebugBar plugin can:
 *  - contribute data to the payload (server-side)
 *  - register one or more tabs (client-side)
 *  - ship JS renderers for those tabs (client-side)
 *
 * Lifecycle:
 *  - boot(): called once after registration (wire listeners, etc.)
 *  - extendPayload(): merge plugin data into the final payload array
 *  - tabs(): describe tabs and provide JS renderers
 */
interface Plugin
{
    /** Unique, stable machine name. E.g. 'session', 'cache', 'exceptions'. */
    public function name(): string;

    /** Optional: called once after registration. */
    public function boot(): void;

    /**
     * Allows the plugin to add or transform payload data before rendering.
     * Return an array to be merged into DebugBar payload under your own keys.
     *
     * Example return: ['session' => [...], 'session_meta' => [...]]
     */
    public function extendPayload(array $payload): array;

    /**
     * Describe UI tabs this plugin wants to add.
     * Each tab entry:
     *  - key: short id (e.g. "session")
     *  - title: human title ("Session")
     *  - icon: small emoji/SVG string (optional)
     *  - order: integer for sorting among all tabs (lower first)
     *  - renderer: JavaScript function body as a string:
     *      function renderSession(d) { /* return HTML string 
     * The renderer must be a JS function named `render<TitleCaseKey>`.
     *
     * @return array<int,array{key:string,title:string,icon?:string,order?:int,renderer:string}>
     **/
    public function tabs(): array;

    /**
     * Enable or disable plugin dynamically.
     */
    public function setEnabled(bool $enabled): void;
    public function isEnabled(): bool;
}
