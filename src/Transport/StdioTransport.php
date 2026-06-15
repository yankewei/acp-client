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

    private string $stderrBuffer = '';

    /**
     * @param array{
     *     command?: string,
     *     args?: string[],
     *     cwd?: string|null,
     *     env?: array<string, string>|null
     * } $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function open(): void
    {
        if ($this->isOpen()) {
            return;
        }

        if ($this->process !== null) {
            $this->close();
        }

        $command = $this->config['command'] ?? '';
        $args = $this->config['args'] ?? [];
        $cwd = $this->config['cwd'] ?? null;
        $env = $this->config['env'] ?? null;

        if ($cwd === '') {
            $cwd = null;
        }

        if ($command === '') {
            throw new TransportException('No command configured for stdio transport');
        }

        $cmd = array_values(array_merge([$command], array_map('strval', $args)));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open($cmd, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            throw new TransportException("Failed to start process: {$command}");
        }

        if (
            !isset($pipes[0], $pipes[1], $pipes[2])
            || !is_resource($pipes[0])
            || !is_resource($pipes[1])
            || !is_resource($pipes[2])
        ) {
            throw new TransportException("Failed to open process pipes: {$command}");
        }

        $this->process = $process;
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        stream_set_blocking($this->stderr, false);
    }

    public function send(string $message): void
    {
        $this->ensureOpen();
        $this->collectStderr();

        $stdin = $this->getStdin();
        $payload = $message . "\n";
        $written = fwrite($stdin, $payload);

        if ($written === false || $written !== strlen($payload)) {
            throw new TransportException($this->withStderr('Failed to write to process stdin'));
        }

        fflush($stdin);
    }

    public function receive(float $timeout = 0.0): ?string
    {
        $stdout = $this->getStdout();

        $this->collectStderr();

        if (feof($stdout)) {
            throw new TransportException($this->withStderr('Process stdout closed'));
        }

        if ($timeout > 0.0) {
            stream_set_blocking($stdout, false);

            $end = microtime(true) + $timeout;
            do {
                $this->collectStderr();

                $line = fgets($stdout);
                if ($line !== false) {
                    stream_set_blocking($stdout, true);
                    return rtrim($line, "\n");
                }

                if (!$this->isOpen()) {
                    stream_set_blocking($stdout, true);
                    throw new TransportException($this->withStderr('Process exited without response'));
                }

                $remaining = $end - microtime(true);
                if ($remaining <= 0.0) {
                    stream_set_blocking($stdout, true);
                    return null;
                }

                $read = [$stdout];
                $write = null;
                $except = null;
                $selected = stream_select(
                    $read,
                    $write,
                    $except,
                    (int) $remaining,
                    (int) (($remaining - (int) $remaining) * 1_000_000),
                );
                if ($selected === false) {
                    stream_set_blocking($stdout, true);
                    throw new TransportException($this->withStderr('Failed to wait for process stdout'));
                }
            } while (true);
        }

        $this->collectStderr();
        $line = fgets($stdout);

        if ($line === false) {
            if (feof($stdout)) {
                throw new TransportException($this->withStderr('Process stdout closed'));
            }

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
        if ($this->process === null) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'] === true;
    }

    private function ensureOpen(): void
    {
        if (!$this->isOpen()) {
            throw new TransportException($this->withStderr('Transport is not open'));
        }
    }

    private function withStderr(string $message): string
    {
        $stderr = $this->readStderr();

        if ($stderr === '') {
            return $message;
        }

        return $message . ': ' . $stderr;
    }

    private function readStderr(): string
    {
        $this->collectStderr();

        return trim($this->stderrBuffer);
    }

    private function collectStderr(): void
    {
        $stderr = $this->stderr;
        if (!is_resource($stderr)) {
            return;
        }

        $contents = stream_get_contents($stderr);
        if ($contents === false || $contents === '') {
            return;
        }

        $this->stderrBuffer .= $contents;

        if (strlen($this->stderrBuffer) > 4000) {
            $this->stderrBuffer = substr($this->stderrBuffer, -4000);
        }
    }

    /**
     * @return resource
     */
    private function getStdin()
    {
        if (!is_resource($this->stdin)) {
            throw new TransportException('Transport stdin is not open');
        }

        return $this->stdin;
    }

    /**
     * @return resource
     */
    private function getStdout()
    {
        if (!is_resource($this->stdout)) {
            throw new TransportException('Transport stdout is not open');
        }

        return $this->stdout;
    }
}
