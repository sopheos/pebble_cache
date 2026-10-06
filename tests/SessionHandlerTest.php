<?php

use Pebble\Cache\MicroCache;
use Pebble\Cache\SessionHandler;
use PHPUnit\Framework\TestCase;

class SessionHandlerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Storage (any PSR-16 cache, MicroCache here)
    // -------------------------------------------------------------------------

    public function testWriteReadDestroy()
    {
        $cache = new MicroCache();
        $handler = new SessionHandler($cache);

        self::assertTrue($handler->write('abc', 'payload'));
        self::assertSame('payload', $handler->read('abc'));
        self::assertSame('payload', $cache->get('sess_abc'));

        self::assertTrue($handler->destroy('abc'));
        self::assertSame('', $handler->read('abc'));
    }

    public function testCustomPrefix()
    {
        $cache = new MicroCache();
        (new SessionHandler($cache, 60, 's:'))->write('abc', 'payload');

        self::assertSame('payload', $cache->get('s:abc'));
    }

    public function testUnknownSessionReadsAsEmptyString()
    {
        self::assertSame('', (new SessionHandler(new MicroCache()))->read('nope'));
    }

    public function testOpenCloseAndGcAreNoOps()
    {
        $handler = new SessionHandler(new MicroCache());

        self::assertTrue($handler->open('/tmp', 'PHPSESSID'));
        self::assertTrue($handler->close());
        self::assertSame(100, $handler->gc(1440));
    }
}
