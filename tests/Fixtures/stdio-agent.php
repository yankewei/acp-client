<?php

declare(strict_types=1);

while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
    if (!is_array($request)) {
        continue;
    }

    if (!array_key_exists('id', $request)) {
        continue;
    }

    echo
        json_encode([
            'jsonrpc' => '2.0',
            'id' => $request['id'],
            'result' => [
                'method' => $request['method'],
                'params' => $request['params'] ?? [],
            ],
        ], flags: JSON_THROW_ON_ERROR) . PHP_EOL
    ;
    flush();
}
