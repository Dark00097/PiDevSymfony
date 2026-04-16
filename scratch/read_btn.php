<?php
$lines = explode("\n", file_get_contents('templates/interfaces/admin/tabs/credits.html.twig'));
foreach($lines as $k => $v) {
    if (strpos($v, 'nx-module-stats-btn') !== false) {
        for($i=max(0,$k-5); $i<$k+5; $i++) echo $i.': '.$lines[$i]."\n";
        break;
    }
}
