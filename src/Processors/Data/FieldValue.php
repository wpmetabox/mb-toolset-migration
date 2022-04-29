<?php
namespace MetaBox\TS\Processors\Data;

use WP_Query;

class FieldValue {
	private $key;
	private $storage;
	private $type;
	private $post_id;
	private $clone;
	private $field_id;

	public function __construct( $args ) {
		$this->key        = $args['key'];
		$this->delete_key = $args['delete_key'] ?? null;
		$this->storage    = $args['storage'];
		$this->type       = $args['type'] ?? null;
		$this->post_id    = $args['post_id'] ?? null;
		$this->clone      = $args['clone'] ?? null;
		$this->field_id   = $args['field_id'] ?? null;
	}

	public function get_value() {
		$method = ( $this->field_id ) ? "get_value_group" : "get_value_{$this->type}";
		$method = method_exists( $this, $method ) ? $method : 'get_value_general';
		$value  = ( $method == 'get_value_group' ) ? $this->get_value_group( $child = null ) : $this->$method();

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

	private function get_value_general_group( $id, $key ) {
		// Get from backup key first.
		$backup_key = '_ts_bak_'.$key;
		$value      = get_post_meta( $id, $backup_key, true );
		if ( ! empty( $value ) ) {
			return $value;
		}

		// Backup the value.
		$value = get_post_meta( $id, 'wpcf-'.$key, true );
		if ( ! empty( $value ) ) {
			update_post_meta( $id, $backup_key, $value );
		}
		return $value;
	}

	private function get_value_group( $child ) {
		$values      = [];
		$sort_order  = [];
		$value_group = [];
		$post_type   = get_post_meta( $this->field_id, '_types_repeatable_field_group_post_type', true );
		$fields      = get_post_meta( $this->field_id, '_wp_types_group_fields', true );
		$fields      = array_filter( explode( ',', $fields ) );
		$sub_fields  = $this->storage->get_id_related_posts( $post_type );
		if ( $child ) {
			$sub_fields = toolset_get_related_posts( $child, $post_type, array( 'query_by_role' => 'parent', 'return' => 'post_id' ) );
		}
		foreach ( $sub_fields as $sub_field ) {
			$value = [];
			$order         = get_post_meta( $sub_field, 'toolset-post-sortorder', true );
			$sort_order[]  = (int) $order - 1;
			foreach ( $fields as $field ) {
				if ( preg_match( '/^_repeatable_group_/', $field ) ) {
					$field_id = explode( '_', $field );
					$field_id = (int) end( $field_id );
					$field_value = new self( [
						'key'        => null,
						'delete_key' => null,
						'storage'    => $this->storage,
						'type'       => null,
						'post_id'    => null,
						'clone'      => null,
						'field_id'   => $field_id,
					] );
					$child_type         = get_post_meta( $field_id, '_types_repeatable_field_group_post_type', true );
					$value[$child_type] = $field_value->get_value_group( $sub_field );
				} else {
					$value[$field] = $this->get_value_general_group( $sub_field, $field );
				}
			}
			$values[] = $value;
		}
		for ( $i = 0 ; $i < count( $values); $i++ ) {
			$value_group[$sort_order[$i]] = $values[$i];
		}
		ksort( $value_group );
		return $value_group;
	}

}