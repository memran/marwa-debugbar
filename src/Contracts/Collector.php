<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Contracts;

use Marwa\DebugBar\Core\DebugState;

/**
 * Contract for a DebugBar Collector.
 * - Metadata is STATIC (no instantiation needed for label/icon/order).
 * - Collection and rendering are INSTANCE-based (lazy instantiation).
 */
interface Collector
{
    /** A short, unique key used as the tab id (e.g., "memory"). */
    public static function key(): string;

    /** Human-readable label to show on the tab (e.g., "Memory"). */
    public static function label(): string;

    /** A tiny icon (emoji or small SVG string) to show next to the label. */
    public static function icon(): string;

    /** Sorting order among tabs (lower first). */
    public static function order(): int;

    /**
     * Collects data. Should be side-effect free and fast.
     * Receive a lightweight DebugState (no coupling to DebugBar internals).
     */
    public function collect(DebugState $state): array;

    /**
     * Returns the HTML for this tab, given the data collected above.
     * Keep the returned string self-contained (no global CSS/JS assumptions).
     */
    public function renderHtml(array $data): string;
}
