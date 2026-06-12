<?php

declare(strict_types=1);

require dirname(__DIR__) . "/vendor/autoload.php";

use Yankewei\AcpClient\Client;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Transport\StdioTransport;

/**
 * @param array<string, mixed> $initialize
 */
function agentSupports(array $initialize, string $capability): bool
{
    return array_key_exists(
        $capability,
        $initialize["agentCapabilities"]["sessionCapabilities"] ?? [],
    );
}

function printJson(string $label, mixed $value): void
{
    echo "\n{$label}:\n";
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        PHP_EOL;
}

/**
 * @param callable(): mixed $callback
 */
function runStep(string $label, callable $callback): mixed
{
    echo "\n== {$label} ==\n";

    try {
        $result = $callback();
        printJson("result", $result);

        return $result;
    } catch (JsonRpcException $e) {
        printJson("json-rpc-error", [
            "code" => $e->getJsonRpcCode(),
            "message" => $e->getMessage(),
            "data" => $e->getData(),
        ]);

        return null;
    }
}

$transport = new StdioTransport([
    "command" => "kimi",
    "args" => ["acp"],
    "cwd" => getcwd(),
]);

$client = new Client($transport, 30.0);

try {
    $initialize = $client->initialize([
        "clientInfo" => [
            "name" => "acp-client-kimi-smoke",
            "title" => "ACP Client Kimi Smoke Test",
            "version" => "0.1.0",
        ],
    ]);

    printJson("initialize", $initialize);

    $session = runStep("sessionNew()", fn() => $client->sessionNew(getcwd()));
    if (!is_array($session) || !is_string($session["sessionId"] ?? null)) {
        throw new RuntimeException("sessionNew() did not return a sessionId");
    }

    $sessionId = $session["sessionId"];

    if (agentSupports($initialize, "list")) {
        runStep(
            "sessionList()",
            fn() => $client->sessionList(getcwd()),
        );
    } else {
        echo "\n== sessionList() ==\nskipped: Kimi did not advertise sessionCapabilities.list\n";
    }

    if (agentSupports($initialize, "resume")) {
        runStep(
            "sessionResume()",
            fn() => $client->sessionResume($sessionId, getcwd()),
        );
    } else {
        echo "\n== sessionResume() ==\nskipped: Kimi did not advertise sessionCapabilities.resume\n";
    }

    if (($initialize["agentCapabilities"]["loadSession"] ?? false) === true) {
        runStep(
            "sessionLoad()",
            fn() => $client->sessionLoad($sessionId, getcwd()),
        );
    } else {
        echo "\n== sessionLoad() ==\nskipped: Kimi did not advertise loadSession\n";
    }

    $modeConfig = null;
    foreach ($session["configOptions"] ?? [] as $configOption) {
        if (($configOption["id"] ?? null) === "mode") {
            $modeConfig = $configOption;
            break;
        }
    }

    if (is_array($modeConfig) && is_string($modeConfig["currentValue"] ?? null)) {
        runStep(
            "setConfigOption()",
            fn() => $client->setConfigOption(
                $sessionId,
                "mode",
                $modeConfig["currentValue"],
            ),
        );
    } else {
        echo "\n== setConfigOption() ==\nskipped: no mode config option returned by Kimi\n";
    }

    runStep(
        "sessionPrompt()",
        fn() => $client->sessionPrompt(
            $sessionId,
            "Reply with exactly: acp-client smoke test ok",
            120.0,
        ),
    );

    echo "\n== sessionCancel() ==\n";
    $client->sessionCancel($sessionId);
    echo "sent notification\n";

    echo "\n== intentionally skipped ==\n";
    echo "- authenticate(): may start Kimi terminal/device login\n";
    echo "- logout(): would log out the local Kimi account\n";
    echo "- sessionDelete(): would remove a session from history if supported\n";
    echo "- sessionClose()/setMode(): Kimi did not advertise those legacy capabilities in this run\n";
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
