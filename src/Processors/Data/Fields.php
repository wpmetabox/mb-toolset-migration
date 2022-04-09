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
		foreach( $this->parent as $id ) {
			$fields = get_post_meta( $id, '_wp_types_group_fields', true );
			$fields = array_filter( explode( ",", $fields ) );
			foreach ( $fields as $field ) {
				$this->field = $field;
				$this->migrate_field( $id );
			}
		}
	}

    private function migrate_field( $id ) {
		$fields   = get_option( 'wpcf-fields' );
		$termmeta = get_option( 'wpcf-termmeta' );
		$usermeta = get_option( 'wpcf-usermeta' );

		$settings = array_merge( $fields, $termmeta, $usermeta );

		$settings = $settings[ $this->field ];

		$ignore_types = [ 'audio', 'skype' ];
		if ( in_array( $settings['type'], $ignore_types ) ) {
			return;
		}

		$args = [
			'settings' => $settings,
			'post_id'  => $id,
			'storage'  => $this->storage,
		];

		$field_type = new FieldType( $args );
		$field_type->migrate();
	}

}
