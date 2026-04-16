<?php
$lines = explode("\n", file_get_contents('templates/interfaces/admin/tabs/accounts.html.twig'));
foreach($lines as $k => $v) {
    if (strpos($v, 'Comptes par statut') !== false) {
        for($i=max(0,$k-15); $i<$k+20; $i++) echo $i.': '.$lines[$i]."\n";
        break;
    }
}
