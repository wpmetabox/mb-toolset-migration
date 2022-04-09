<?php
namespace MetaBox\TS\Processors\Data;

use WP_Query;

class FieldValue {
	private $key;
	private $storage;
	private $type;
	private $post_id;
	private $clone;

	public function __construct( $args ) {
		$this->key        = $args['key'];
		$this->delete_key = $args['delete_key'] ?? null;
		$this->storage    = $args['storage'];
		$this->type    = $args['type'] ?? null;
		$this->post_id = $args['post_id'] ?? null;
		$this->clone   = $args['clone'] ?? null;
	}

	public function get_value() {
		$method = "get_value_{$this->type}";
		$method = method_exists( $this, $method ) ? $method : 'get_value_general';

		$value = $this->$method();

		// Delete extra key.
		if ( $this->delete_key ) {
			$this->storage->delete( $this->delete_key );
		}

		return $value;
	}

	private function get_value_general() {

		// Get from backup key first.
		$backup_key = "_ts_bak_{$this->key}";
		$value      = $this->storage->get( $backup_key );
		$value      = ( $this->clone && !is_array( $value ) ) ? $this->storage->get_all( $backup_key ) : $this->storage->get( $backup_key );
		if ( ! empty( $value ) ) {
			return $value;
		}

		// Backup the value.
		$value = $this->storage->get( 'wpcf-'.$this->key );
		$value = ( $this->clone && !is_array( $value ) ) ? $this->storage->get_all( 'wpcf-'.$this->key ) : $this->storage->get( 'wpcf-'.$this->key );
		if ( ! empty( $value ) ) {
			$this->storage->update( $backup_key, $value );
		}

		return $value;
	}

}