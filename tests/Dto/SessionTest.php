<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\ConfigOption;
use Yankewei\AcpClient\Dto\Session;

final class SessionTest extends TestCase
{
    public function testFromArrayParsesSessionId(): void
    {
        $session = Session::fromArray(['sessionId' => 'sess_1']);

        static::assertSame('sess_1', $session->getSessionId());
        static::assertSame([], $session->getConfigOptions());
    }

    public function testFromArrayParsesConfigOptions(): void
    {
        $session = Session::fromArray([
            'sessionId' => 'sess_1',
            'configOptions' => [
                [
                    'id' => 'mode',
                    'name' => 'Session Mode',
                    'description' => 'Controls permissions',
                    'category' => 'mode',
                    'type' => 'select',
                    'currentValue' => 'code',
                    'options' => [
                        [
                            'value' => 'ask',
                            'name' => 'Ask',
                            'description' => 'Ask first',
                        ],
                        [
                            'value' => 'code',
                            'name' => 'Code',
                        ],
                    ],
                ],
            ],
        ]);

        $options = $session->getConfigOptionObjects();
        static::assertCount(1, $options);
        static::assertInstanceOf(ConfigOption::class, $options[0]);
        static::assertSame('mode', $options[0]->getId());
        static::assertSame('Session Mode', $options[0]->getName());
        static::assertSame('Controls permissions', $options[0]->getDescription());
        static::assertSame('mode', $options[0]->getCategory());
        static::assertSame('select', $options[0]->getType());
        static::assertSame('code', $options[0]->getCurrentValue());
        static::assertTrue($options[0]->hasValue('ask'));
        static::assertTrue($options[0]->hasValue('code'));
        static::assertFalse($options[0]->hasValue('architect'));
        static::assertSame($options[0], $session->getConfigOption('mode'));
        static::assertNull($session->getConfigOption('model'));

        static::assertSame(
            [
                [
                    'id' => 'mode',
                    'name' => 'Session Mode',
                    'type' => 'select',
                    'currentValue' => 'code',
                    'options' => [
                        [
                            'value' => 'ask',
                            'name' => 'Ask',
                            'description' => 'Ask first',
                        ],
                        [
                            'value' => 'code',
                            'name' => 'Code',
                        ],
                    ],
                    'description' => 'Controls permissions',
                    'category' => 'mode',
                ],
            ],
            $session->getConfigOptions(),
        );
    }

    public function testFromArrayAllowsMissingFields(): void
    {
        $session = Session::fromArray(['ready' => true]);

        static::assertNull($session->getSessionId());
        static::assertSame([], $session->getConfigOptions());
    }
}
