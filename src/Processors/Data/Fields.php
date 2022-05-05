<?php
namespace MetaBox\TS\Processors\Data;

class Fields {
	private $parent;
	private $storage;
	private $field;

	public function __construct( $parent, $storage ) {
		$this->parent  = $parent;
		$this->storage = $storage;
	}

	public function migrate_fields() {
		foreach( $this->parent as $post_id ) {
			$fields = get_post_meta( $post_id, '_wp_types_group_fields', true );
			$fields = array_filter( explode( ",", $fields ) );
			foreach ( $fields as $field ) {
				$this->field = $field;
				$this->migrate_field( $post_id );
			}
		}
	}

    private function migrate_field( $post_id ) {
		$fields   = get_option( 'wpcf-fields' ) ?: [];
		$termmeta = get_option( 'wpcf-termmeta' ) ?: [];
		$usermeta = get_option( 'wpcf-usermeta' ) ?: [];

		$settings = array_merge( $fields, $termmeta, $usermeta );

		$settings = $settings[ $this->field ];
		$ignore_types = [ 'skype', 'post' ];
		if ( in_array( $settings['type'], $ignore_types ) ) {
			return;
		}
		if ( preg_match( '/^_repeatable_group_/', $this->field ) ) {
			$field_id = explode( '_', $this->field );
			$field_id = (int) end( $field_id );
		}

		$args = [
			'settings' => $settings,
			'post_id'  => $post_id,
			'storage'  => $this->storage,
			'field_id' => !empty( $field_id ) ? $field_id : ''
		];

		$field_type = new FieldType( $args );
		$field_type->migrate();
	}

}
