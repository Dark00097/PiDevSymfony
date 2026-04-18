<?php
require __DIR__ . '/vendor/autoload.php';
$index = file(__DIR__ . '/var/cache/dev/profiler/index.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$rows = array_slice($index, -200);
$root = __DIR__ . '/var/cache/dev/profiler';
foreach ($rows as $line) {
    [$token, $ip, $method, $url, $time, $parent, $status, $type] = array_pad(str_getcsv($line), 8, '');
    if (stripos($url, '/admin?tab=users') === false || strtoupper($method) !== 'POST') {
        continue;
    }
    $path = sprintf('%s/%s/%s/%s', $root, substr($token, 4, 2), substr($token, 2, 2), $token);
    if (!is_file($path)) continue;

    $decoded = @gzdecode((string) file_get_contents($path));
    if ($decoded === false) continue;
    $payload = @unserialize($decoded);
    if (!is_array($payload)) continue;

    $collector = $payload['data']['request'] ?? null;
    if (!$collector instanceof Symfony\Component\HttpKernel\DataCollector\RequestDataCollector) continue;

    $requestBag = $collector->getRequestRequest();
    $requestData = $requestBag instanceof Symfony\Component\HttpFoundation\ParameterBag ? $requestBag->all() : [];
    $action = (string) ($requestData['action'] ?? '');
    $idUser = array_key_exists('idUser', $requestData) ? (string) $requestData['idUser'] : '(missing)';
    $nom = (string) ($requestData['nom'] ?? '');
    $prenom = (string) ($requestData['prenom'] ?? '');
    $email = (string) ($requestData['email'] ?? '');

    echo "token={$token} action={$action} idUser={$idUser} nom={$nom} prenom={$prenom} email={$email}\n";
}
