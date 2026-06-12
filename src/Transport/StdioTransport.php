<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Transport;

use Yankewei\AcpClient\Exception\TransportException;

final class StdioTransport implements TransportInterface
{
    /** @var resource|null */
    private $process = null;

    /** @var resource|null */
    private $stdin = null;

    /** @var resource|null */
    private $stdout = null;

    /** @var resource|null */
    private $stderr = null;

    public function __construct(
        private readonly array $config,
    ) {
    }

    public function open(): void
    {
        if ($this->isOpen()) {
            return;
        }

        $command = $this->config['command'] ?? '';
        $args = $this->config['args'] ?? [];
        $cwd = $this->config['cwd'] ?? null;
        $env = $this->config['env'] ?? null;

        if ($command === '') {
            throw new TransportException('No command configured for stdio transport');
        }

        $cmd = escapeshellcmd($command);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg((string) $arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            throw new TransportException("Failed to start process: {$command}");
        }

        $this->process = $process;
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];
    }

    public function send(string $message): void
    {
        $this->ensureOpen();

        $written = fwrite($this->stdin, $message . "\n");

        if ($written === false) {
            throw new TransportException('Failed to write to process stdin');
        }

        fflush($this->stdin);
    }

    public function receive(float $timeout = 0.0): ?string
    {
        $this->ensureOpen();

        if (feof($this->stdout)) {
            return null;
        }

        if ($timeout > 0.0) {
            stream_set_blocking($this->stdout, false);

            $end = microtime(true) + $timeout;
            do {
                $line = fgets($this->stdout);
                if ($line !== false) {
                    stream_set_blocking($this->stdout, true);
                    return rtrim($line, "\n");
                }

                $remaining = $end - microtime(true);
                if ($remaining <= 0.0) {
                    stream_set_blocking($this->stdout, true);
                    return null;
                }

                $read = [$this->stdout];
                $write = null;
                $except = null;
                stream_select($read, $write, $except, (int) $remaining, (int) (($remaining - (int) $remaining) * 1_000_000));
            } while (true);
        }

        $line = fgets($this->stdout);

        if ($line === false) {
            return null;
        }

        return rtrim($line, "\n");
    }

    public function close(): void
    {
        if ($this->stdin !== null) {
            fclose($this->stdin);
            $this->stdin = null;
        }

        if ($this->stdout !== null) {
            fclose($this->stdout);
            $this->stdout = null;
        }

        if ($this->stderr !== null) {
            fclose($this->stderr);
            $this->stderr = null;
        }

        if ($this->process !== null) {
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function isOpen(): bool
    {
        return $this->process !== null;
    }

    private function ensureOpen(): void
    {
        if (!$this->isOpen()) {
            throw new TransportException('Transport is not open');
        }
    }
}
