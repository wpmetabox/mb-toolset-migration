<?php
namespace MetaBox\TS\Processors\FieldGroups;

use MetaBox\Support\Arr;

class FieldType {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function __get( $name ) {
		return $this->settings[ $name ] ?? null;
	}

	public function __set( $name, $value ) {
		return $this->settings[ $name ] = $value;
	}

	public function __isset( $name ) {
		return isset( $this->settings[ $name ] );
	}

	public function __unset( $name ) {
		unset( $this->settings[ $name ] );
	}

	public function migrate() {
		$this->migrate_general_settings();

		$method = "migrate_{$this->type}";
		if ( method_exists( $this, $method ) ) {
			$this->$method();
		}

		return $this->settings;
	}

	private function migrate_general_settings() {
		Arr::change_key( $this->settings, 'description', 'label_description' );
		$this->std         = Arr::get( $this->settings, 'data.user_default_value' );
		$this->placeholder = Arr::get( $this->settings, 'data.placeholder' );

		if ( Arr::get( $this->settings, 'data.repetitive' ) ) {
			$this->clone         = true;
			$this->sort_clone    = true;
			$this->clone_default = true;
		} else {
			unset( $this->clone );
		}

		if ( Arr::get( $this->settings, 'data.validate.required' ) ) {
			$this->required = true;
		} else {
			unset( $this->required );
		}

		$this->_id    = $this->type . '_' . uniqid();
		$this->_state = 'collapse';
		//unset( $this->data );
		unset( $this->meta_key );
		unset( $this->meta_type );

	}

	private function migrate_phone() {
		$this->type = 'tel';
	}

	private function migrate_textfield() {
		$this->type = 'text';
	}

	private function migrate_embed() {
		$this->type = 'oembed';
	}

	private function migrate_image() {
		$this->type = 'single_image';
	}

	private function migrate_numeric() {
		$this->type = 'number';
	}

	private function migrate_select() {
		$this->migrate_choices();
	}

	private function migrate_radio() {
		$this->migrate_choices();
	}

	private function migrate_checkbox() {
		$this->std = Arr::get( $this->settings, 'data.checked' );
	}

	private function migrate_checkboxes() {
		$this->type = 'checkbox_list';
		$values     = [];
		$default    = [];
		$options    = Arr::get( $this->settings, 'data.options' );

		foreach ( $options as $key => $option ) {
			$title    = Arr::get( $option, 'title' );
			$value    = Arr::get( $option, 'set_value' );
			$checked  = Arr::get( $option, 'checked' );
			if ( $title && $value ) {
				$values[] = "$value: $title";
			}
			if ( $checked ) {
				$default[] = $value;
			}

		}
		$this->options = implode( "\n", $values );
		$this->std     = implode( "\n", $default );
	}

	private function migrate_choices() {
		$values        = [];
		$options       = Arr::get( $this->settings, 'data.options' );
		$default       = Arr::get( $options, 'default' );
		$default_value = '';

		foreach ( $options as $key => $option ) {
			$title    = Arr::get( $option, 'title' );
			$value    = Arr::get( $option, 'value' );
			if ( $title && $value ){
				$values[] = "$value: $title";
			}
			if ( $key == $default ) {
				$default_value = Arr::get( $option, 'value' );
			}

		}
		$this->options = implode( "\n", $values );
		$this->std     = $default_value;

	}

	private function migrate_post_object() {
		$this->type = 'post';

		if ( isset( $this->taxonomy ) && is_array( $this->taxonomy ) ) {
			$query_args = [];
			foreach ( $this->taxonomy as $k => $item ) {
				list( $taxonomy, $slug ) = explode( ':', $item );

				$id = uniqid();
				$query_args[ $id ] = [
					'id'    => $id,
					'key'   => "tax_query.$k.taxonomy",
					'value' => $taxonomy,
				];

				$id = uniqid();
				$query_args[ $id ] = [
					'id'    => $id,
					'key'   => "tax_query.$k.field",
					'value' => 'slug',
				];

				$id = uniqid();
				$query_args[ $id ] = [
					'id'    => $id,
					'key'   => "tax_query.$k.terms",
					'value' => $slug,
				];
			}

			$this->query_args = $query_args;
		}

		$this->multiple = (bool) $this->multiple;

		unset( $this->allow_null );
		unset( $this->ui );
	}

	private function migrate_relationship() {
		$this->migrate_post_object();
		$this->multiple = true;

		unset( $this->elements );
		unset( $this->min );
		unset( $this->max );
	}

	private function migrate_date() {
		$date_and_time = Arr::get( $this->settings, 'data.date_and_time' );
		$this->type    = ( $date_and_time == 'date' ) ? 'date' : 'datetime';
	}


	private function migrate_colorpicker() {
		$this->type = 'color';
	}

	private function migrate_group() {
		$fields = new Fields( $this->post_id );

		$this->fields = $fields->migrate_fields();

		unset( $this->layout );
		unset( $this->sub_fields );
	}

}