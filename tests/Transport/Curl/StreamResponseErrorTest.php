<?php

namespace WpOrg\Requests\Tests\Transport\Curl;

use ReflectionProperty;
use WpOrg\Requests\Exception;
use WpOrg\Requests\Hooks;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Tests\TestCase;
use WpOrg\Requests\Transport\Curl;

/**
 * @covers \WpOrg\Requests\Transport\Curl::stream_response
 * @covers \WpOrg\Requests\Transport\Curl::release_stream_handle
 */
final class StreamResponseErrorTest extends TestCase {

	public function testFailedStreamedRequestReleasesTheHandle() {
		if (Curl::test() === false) {
			$this->markTestSkipped('cURL transport is not available');
		}

		// A listener which accepts the connection but never responds.
		$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$url    = 'http://' . stream_socket_get_name($server, false) . '/';

		$transport = new Curl();
		$options   = Requests::OPTION_DEFAULTS;

		$options['stream']          = true;
		$options['timeout']         = 1;
		$options['connect_timeout'] = 5;
		$options['hooks']           = new Hooks();

		try {
			$transport->request($url, [], [], $options);
			$this->fail('A streamed request to a stalled server should throw');
		} catch (Exception $e) {
			$this->assertSame('timeout', $e->getType());
		} finally {
			fclose($server);
		}

		// The handle was reconfigured for streaming; it must be dropped so the
		// next request on this instance starts from a freshly initialised one.
		$property = new ReflectionProperty(Curl::class, 'handle');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$this->assertNull($property->getValue($transport), 'Transport should not keep the streaming-configured handle after a failed streamed request');
	}
}
