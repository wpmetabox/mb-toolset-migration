<?php
namespace MetaBox\TS\Processors\Data;
use MetaBox\Support\Arr;

class FieldType {
	private $settings;
	private $storage;
	private $field_value;
	private $field_id;

	public function __construct( $args ) {
		$this->settings    = $args['settings'];
		$this->storage     = $args['storage'];
		$this->field_id    = $args['field_id'];
		$this->field_value = new FieldValue( [
			'key'      => $args['settings']['id'],
			'storage'  => $args['storage'],
			'type'     => $args['settings']['type'],
			'post_id'  => $args['post_id'],
			'clone'    => Arr::get( $this->settings, 'data.repetitive' ),
			'field_id' => $args['field_id']
		] );
	}

	public function migrate() {
		// Always delete redundant key.
		$this->storage->delete( "_{$this->settings['id']}" );

		$method = ( $this->field_id ) ? 'migrate_group' : "migrate_{$this->settings['type']}";
		$method = method_exists( $this, $method ) ? $method : 'migrate_general';
		$this->$method();
	}

	private function migrate_general() {
		$value = $this->field_value->get_value();
		$this->storage->update( $this->settings['id'], $value );
	}

	private function migrate_group() {
		$id    = get_post_meta( $this->field_id, '_types_repeatable_field_group_post_type', true );
		$value = $this->field_value->get_value();
		$this->storage->update( $id, $value );
	}

	private function migrate_image() {
		$this->migrate_image_file_video();
	}


	private function migrate_file() {
		$this->migrate_image_file_video();
	}

	private function migrate_video() {
		$this->migrate_image_file_video();
	}

	private function migrate_checkboxes() {
		$this->migrate_multiple();
	}

	private function migrate_post_object() {
		$this->migrate_multiple();
	}

	private function migrate_relationship() {
		$this->migrate_multiple( true );
	}

	private function migrate_user() {
		$this->migrate_multiple();
	}

	private function migrate_multiple() {
		$value = $this->field_value->get_value();
		if ( empty( $value ) || ! is_array( $value ) ) {
			return;
		}
		$this->storage->delete( $this->settings['id'] );
		foreach ( $value as $sub_value ) {
			foreach( $sub_value as $sub_sub_value)
			$this->storage->add( $this->settings['id'], $sub_sub_value );
		}
	}

	private function get_image_file_video_id( $url ) {
		global $wpdb;
		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url ) );
		return $attachment[0];
	}

	private function migrate_image_file_video() {
		$values = $this->field_value->get_value();
		$clone = Arr::get( $this->settings, 'data.repetitive' );
		if ( $clone ) {
			$meta_values = [];
			foreach( $values as $value ) {
				$meta_values[] = $this->get_image_file_video_id( $value );
			}
			$this->storage->update( $this->settings['id'], $meta_values );
		} else {
			$meta_value = $this->get_image_file_video_id( $values );
			$this->storage->update( $this->settings['id'], $meta_value );
		}
	}
}