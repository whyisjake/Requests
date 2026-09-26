<?php

namespace WpOrg\Requests\Tests\Response\Stream;

use WpOrg\Requests\Exception;
use WpOrg\Requests\Hooks;
use WpOrg\Requests\Response\Stream\Socket;
use WpOrg\Requests\Tests\TestCase;

/**
 * @covers \WpOrg\Requests\Response\Stream\Socket::fetch
 */
final class FetchTest extends TestCase {

	public function testReadThrowsTimeoutWhenNoDataArrives() {
		if (DIRECTORY_SEPARATOR === '\\') {
			$this->markTestSkipped('stream_socket_pair() with STREAM_PF_UNIX is not available on Windows');
		}

		// A connected socket pair where the peer never writes: the body
		// phase stalls after the headers have already been consumed.
		$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		stream_set_timeout($pair[0], 0, 200000);

		$stream = new Socket($pair[0], false, false, new Hooks());

		try {
			$this->expectException(Exception::class);
			$this->expectExceptionMessage('fsocket timed out');

			$stream->read();
		} finally {
			$stream->close();
			fclose($pair[1]);
		}
	}
}
