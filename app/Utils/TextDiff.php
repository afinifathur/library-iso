<?php
namespace App\Utils;

class TextDiff
{
    // minimal: line by line diff producing HTML with <ins> and <del>
    public static function htmlDiff(string $old, string $new): string
    {
        $a = preg_split("/\r?\n/", trim($old));
        $b = preg_split("/\r?\n/", trim($new));
        // build LCS table
        $n = count($a); $m = count($b);
        $L = array_fill(0,$n+1, array_fill(0,$m+1, 0));
        for ($i = $n-1; $i>=0; $i--) {
            for ($j = $m-1; $j>=0; $j--) {
                if ($a[$i] === $b[$j]) $L[$i][$j] = 1 + $L[$i+1][$j+1];
                else $L[$i][$j] = max($L[$i+1][$j], $L[$i][$j+1]);
            }
        }
        // reconstruct diff
        $i=0;$j=0;
        $out = '';
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out .= '<div class="line same">'.htmlspecialchars($a[$i]).'</div>';
                $i++; $j++;
            } elseif ($L[$i+1][$j] >= $L[$i][$j+1]) {
                $out .= '<div class="line removed">- '.htmlspecialchars($a[$i]).'</div>';
                $i++;
            } else {
                $out .= '<div class="line added">+ '.htmlspecialchars($b[$j]).'</div>';
                $j++;
            }
        }
        while ($i < $n) { $out .= '<div class="line removed">- '.htmlspecialchars($a[$i]).'</div>'; $i++; }
        while ($j < $m) { $out .= '<div class="line added">+ '.htmlspecialchars($b[$j]).'</div>'; $j++; }
        return $out;
    }
}
