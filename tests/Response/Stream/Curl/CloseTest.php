<?php

namespace WpOrg\Requests\Tests\Response\Stream\Curl;

use WpOrg\Requests\Hooks;
use WpOrg\Requests\Response\Stream\Curl;
use WpOrg\Requests\Tests\TestCase;

/**
 * @covers \WpOrg\Requests\Response\Stream\Curl::free
 * @covers \WpOrg\Requests\Response\Stream::close
 * @covers \WpOrg\Requests\Response\Stream::__destruct
 */
final class CloseTest extends TestCase {

	public function testCloseStopsReads() {
		$stream = $this->makeStream('abc');

		$stream->close();

		$this->assertTrue($stream->eof(), 'Stream should be at EOF after close()');
		$this->assertSame('', $stream->read(3), 'Reading a closed stream should return an empty string');
	}

	public function testCloseIsIdempotent() {
		$stream = $this->makeStream('abc');

		$stream->close();
		$stream->close();

		$this->assertTrue($stream->eof());
	}

	public function testDestructorReleasesTheHandles() {
		if (class_exists('WeakReference') === false) {
			$this->markTestSkipped('WeakReference (PHP 7.4+) is needed to observe the stream being released');
		}

		$handle = curl_init('http://127.0.0.1:1/');
		$multi  = curl_multi_init();
		curl_multi_add_handle($multi, $handle);

		$stream = new Curl($multi, $handle, 'abc', 1, false, new Hooks());
		$weak   = \WeakReference::create($stream); // phpcs:ignore PHPCompatibility.Classes.NewClasses.weakreferenceFound -- Guarded by the class_exists() check above.

		// Dropping the last reference must release the stream by refcount
		// alone: no gc_collect_cycles() here on purpose. A callback on the
		// cURL handle which referenced the stream would keep it alive.
		unset($stream);

		$this->assertNull($weak->get(), 'Stream should be destructed as soon as its last reference is dropped');

		if (is_resource($handle)) {
			// PHP < 8.0: handles are resources and are closed by the destructor.
			$this->assertFalse(is_resource($handle), 'cURL easy handle should be closed on destruct');
		}
	}

	public function testDroppedStreamReleasesTheConnection() {
		if (class_exists('WeakReference') === false) {
			$this->markTestSkipped('WeakReference (PHP 7.4+) is needed to observe the stream being released');
		}

		// A listener which accepts the connection but never responds.
		$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$handle = curl_init('http://' . stream_socket_get_name($server, false) . '/');
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		$multi = curl_multi_init();
		curl_multi_add_handle($multi, $handle);

		$stream = new Curl($multi, $handle, '', 1, false, new Hooks());
		$weak   = \WeakReference::create($stream); // phpcs:ignore PHPCompatibility.Classes.NewClasses.weakreferenceFound -- Guarded by the class_exists() check above.

		try {
			// Start the transfer so the callbacks are live on the handle.
			$running = 0;
			do {
				$status = curl_multi_exec($multi, $running);
			} while ($status === CURLM_CALL_MULTI_PERFORM);

			unset($stream);

			$this->assertNull($weak->get(), 'Stream with a live transfer should still be released by refcount');
		} finally {
			fclose($server);
		}
	}

	/**
	 * Build a stream around freshly initialised cURL handles.
	 *
	 * @param string $prebuffered Body bytes received before the handoff.
	 *
	 * @return \WpOrg\Requests\Response\Stream\Curl
	 */
	private function makeStream($prebuffered = '') {
		$handle = curl_init('http://127.0.0.1:1/');
		$multi  = curl_multi_init();
		curl_multi_add_handle($multi, $handle);

		return new Curl($multi, $handle, $prebuffered, 1, false, new Hooks());
	}
}
