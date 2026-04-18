<?php
$lines = explode("\n", file_get_contents('C:\PiDevSymfony\templates\interfaces\admin\tabs\credits.html.twig'));
foreach($lines as $k => $line) {
    if (strpos($line, 'nx-credit-row') !== false) {
        for ($i=0; $i<15; $i++) echo $lines[$k+$i]."\n";
        break;
    }
}
