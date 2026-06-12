<?php

declare(strict_types=1);

require dirname(__DIR__) . "/vendor/autoload.php";

use Yankewei\AcpClient\Client;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Transport\StdioTransport;

$transport = new StdioTransport([
    "command" => "kimi",
    "args" => ["acp"],
    "cwd" => getcwd(),
]);

$client = new Client($transport, 15.0);

try {
    $initialize = $client->initialize([
        "clientInfo" => [
            "name" => "acp-client-kimi-smoke",
            "title" => "ACP Client Kimi Smoke Test",
            "version" => "0.1.0",
        ],
    ]);

    echo "initialize:\n";
    echo json_encode($initialize, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        PHP_EOL;

    $session = $client->call("session/new", [
        "cwd" => getcwd(),
        "mcpServers" => [],
    ]);

    echo "\nsession/new:\n";
    echo json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        PHP_EOL;
} catch (JsonRpcException $e) {
    fwrite(
        STDERR,
        "JSON-RPC error " .
            $e->getJsonRpcCode() .
            ": " .
            $e->getMessage() .
            PHP_EOL,
    );
    fwrite(
        STDERR,
        "Data: " .
            json_encode(
                $e->getData(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) .
            PHP_EOL,
    );
    exit(1);
} finally {
    $transport->close();
}
