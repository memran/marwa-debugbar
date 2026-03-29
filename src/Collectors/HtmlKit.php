<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

trait HtmlKit
{
    /**
     * @param array<string,array{title:string,html:string}> $tabs
     */
    private function tabsNav(array $tabs): string
    {
        $output = '';
        $index = 0;

        foreach ($tabs as $key => $definition) {
            $safeKey = $this->jsKey($key);
            $title = $this->e($definition['title']);
            $selected = $index === 0 ? 'true' : 'false';
            $tabIndex = $index === 0 ? '0' : '-1';
            $activeClass = $index === 0 ? ' active' : '';

            $output .= <<<HTML
<button role="tab" aria-selected="{$selected}" tabindex="{$tabIndex}" data-key="{$safeKey}" class="mw-tab-btn{$activeClass}">{$title}</button>
HTML;
            $index++;
        }

        return $output;
    }

    /**
     * @param array<string,array{title:string,html:string}> $tabs
     */
    private function tabsPanels(array $tabs): string
    {
        $output = '';
        $index = 0;

        foreach ($tabs as $key => $definition) {
            $safeKey = $this->jsKey($key);
            $display = $index === 0 ? '' : 'style="display:none"';
            $hidden = $index === 0 ? 'false' : 'true';

            $output .= <<<HTML
<div role="tabpanel" aria-hidden="{$hidden}" data-key="{$safeKey}" {$display}>
  {$definition['html']}
</div>
HTML;
            $index++;
        }

        return $output;
    }

    private function card(string $title, string $bodyHtml): string
    {
        return '<section class="mw-card"><div class="mw-card-h">' . $this->e($title) . '</div><div class="mw-card-b">' . $bodyHtml . '</div></section>';
    }

    /**
     * @param list<string> $headers
     * @param list<?string> $align
     */
    private function table(array $headers, string $rowsHtml, array $align): string
    {
        $headerHtml = '';
        foreach ($headers as $index => $header) {
            $class = ($align[$index] ?? null) === 'right' ? ' class="mw-right"' : '';
            $headerHtml .= '<th' . $class . '>' . $this->e($header) . '</th>';
        }

        return '<table class="mw-table"><thead><tr>' . $headerHtml . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';
    }

    /**
     * @param list<string> $cells
     * @param list<?string> $align
     */
    private function tr(array $cells, array $align): string
    {
        $cellHtml = '';
        foreach ($cells as $index => $cell) {
            $class = ($align[$index] ?? null) === 'right' ? ' class="mw-right"' : '';
            $cellHtml .= '<td' . $class . '>' . $cell . '</td>';
        }

        return '<tr>' . $cellHtml . '</tr>';
    }

    private function stat(string $label, string $value): string
    {
        return '<div class="mw-card"><div class="mw-card-h">' . $this->e($label) . '</div><div class="mw-card-b">' . $value . '</div></div>';
    }

    private function preJson(mixed $value): string
    {
        return '<pre class="mw-pre">' . $this->e($this->encodeJson($value)) . '</pre>';
    }

    private function encodeJson(mixed $value): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return json_encode(['error' => 'Unable to encode value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        return $json;
    }

    private function num(float $number, int $decimals = 2): string
    {
        return number_format($number, $decimals, '.', '');
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function jsKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) ?: 'tab';
    }
}
