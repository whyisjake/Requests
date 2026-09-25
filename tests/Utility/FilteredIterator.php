<?php

class RequestsTest_Utility_FilteredIterator extends PHPUnit_Framework_TestCase {
	/**
	 * @dataProvider data_serialize_deserialize_objects
	 */
	function test_deserialize_request_utility_filtered_iterator_objects( $value ) {
		$serialized = serialize( $value );
		if ( get_class( $value ) === 'Requests_Utility_FilteredIterator' ) {
			$new_value = unserialize( $serialized );
			$reflection = new ReflectionClass( 'Requests_Utility_FilteredIterator' );
			$property   = $reflection->getProperty( 'callback' );
			$property->setAccessible( true );
			$callback_value = $property->getValue( $new_value );
			$this->assertSame( null, $callback_value );
		} else {
			$this->assertEquals( $value->count(), unserialize( $serialized )->count() );
		}
	}

	function data_serialize_deserialize_objects() {
		return array(
			array( new Requests_Utility_FilteredIterator( array( 1 ), 'md5' ) ),
			array( new Requests_Utility_FilteredIterator( array( 1, 2 ), 'sha1' ) ),
			array( new ArrayIterator( array( 1, 2, 3 ) ) ),
		);
	}
}
