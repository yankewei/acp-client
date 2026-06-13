<?php

declare(strict_types=1);

$line = fgets(STDIN);
if ($line === false) {
    exit(0);
}

$request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($request)) {
    exit(0);
}

echo json_encode([
    'jsonrpc' => '2.0',
    'id' => $request['id'],
    'result' => ['ok' => true],
], JSON_THROW_ON_ERROR) . PHP_EOL;
flush();
