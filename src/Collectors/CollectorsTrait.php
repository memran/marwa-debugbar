<?php  declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

trait CollectorsTrait{
    
     private function tabsNav(array $tabs): string
    {
        $out = '';
        $i = 0;
        foreach ($tabs as $key => $def) {
            $k = $this->jsKey($key);
            $title = $this->e($def['title']);
            $selected = $i===0 ? 'true' : 'false';
            $tabIndex = $i===0 ? '0' : '-1';
            $out .= <<<HTML
<button role="tab" aria-selected="{$selected}" tabindex="{$tabIndex}" data-key="{$k}" class="mw-tab-btn">{$title}</button>
HTML;
            $i++;
        }
        return $out;
    }

    private function tabsPanels(array $tabs): string
    {
        $out = '';
        $i = 0;
        foreach ($tabs as $key => $def) {
            $k = $this->jsKey($key);
            $display = $i===0 ? '' : 'style="display:none"';
            // For very long tabs we already wrapped internally where needed
            $out .= <<<HTML
<div role="tabpanel" aria-hidden="{$this->e($i===0 ? 'false' : 'true')}" data-key="{$k}" {$display}>
  {$def['html']}
</div>
HTML;
            $i++;
        }
        return $out;
    }

    private function card(string $title, string $bodyHtml): string
    {
        return '<section class="mw-card">'
             .   '<div class="mw-card-h">'.$this->e($title).'</div>'
             .   '<div class="mw-card-b">'.$bodyHtml.'</div>'
             . '</section>';
    }

    private function table(array $headers, string $rowsHtml, array $align): string
    {
        $ths = '';
        foreach ($headers as $i => $h) {
            $ths .= '<th'.(($align[$i]??null)==='right'?' class="mw-right"':'').'>'.$this->e($h).'</th>';
        }
        return '<table class="mw-table"><thead><tr>'.$ths.'</tr></thead><tbody>'.$rowsHtml.'</tbody></table>';
    }

    private function tr(array $cells, array $align): string
    {
        $tds = '';
        foreach ($cells as $i => $c) {
            $tds .= '<td'.(($align[$i]??null)==='right'?' class="mw-right"':'').'>'.$c.'</td>';
        }
        return '<tr>'.$tds.'</tr>';
    }

    private function stat(string $label, string $value): string
    {
        return '<div class="mw-card"><div class="mw-card-h">'.$this->e($label).'</div><div class="mw-card-b">'.$value.'</div></div>';
    }

    private function kpiRow(array $pairs): string
    {
        $out = '';
        foreach ($pairs as [$label, $value]) {
            $out .= '<div class="mw-kpi"><span class="k">'.$this->e($label).'</span><span class="v">'.$this->e((string)$value).'</span></div>';
        }
        return $out;
    }

    private function preJson(array $a): string
    {
        $j = $this->e(json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return '<pre class="mw-pre">'.$j.'</pre>';
    }

    private function num(float $n, int $dec = 2): string
    {
        //return $this->millisecondsToDate($n);
        return number_format($n, $dec, '.', '');
    }

    private function e(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }

    private function jsKey(string $s): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $s) ?: 'tab';
    }
}
