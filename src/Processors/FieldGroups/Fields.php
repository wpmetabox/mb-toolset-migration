<?php
namespace MetaBox\TS\Processors\FieldGroups;

use WP_Query;

class Fields {
	private $parent;
	private $fields = [];
	private $field;

	public function __construct( $parent ) {
		$this->parent = $parent;
	}

	public function migrate_fields() {

		$fields = get_post_meta( $this->parent, '_wp_types_group_fields', true );
		$fields = array_filter( explode( ",", $fields ) );

		foreach ( $fields as $field ) {
			$this->field = $field;
			$this->migrate_field();
		}
		return $this->fields;
	}

	private function migrate_field() {
		$fields   = get_option( 'wpcf-fields' );
		$termmeta = get_option( 'wpcf-termmeta' );
		$usermeta = get_option( 'wpcf-usermeta' );

		$settings = array_merge( $fields, $termmeta, $usermeta );

		$settings = $settings[ $this->field ];

		$ignore_types = [ 'audio', 'skype' ];
		if ( in_array( $settings['type'], $ignore_types ) ) {
			return;
		}

		$field_type = new FieldType( $settings );
		$settings   = $field_type->migrate();

		$conditional_logic = new ConditionalLogic( $settings );
		$conditional_logic->migrate();
		$this->fields[ $settings['_id'] ] = $settings;
	}
}
