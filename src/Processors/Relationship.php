<?php
namespace MetaBox\TS\Processors;

use MetaBox\Support\Arr;
use WP_Query;

class Relationship extends Base {

	protected function get_items() {

		$data_rs = $this->get_id_relationship();

		if ( empty( $data_rs ) ) {
			return [];
		}

		return $data_rs;
	}

	private function get_id_relationship() {
		global $wpdb;
		$sql = "SELECT id FROM `{$wpdb->prefix}toolset_relationships` WHERE origin='wizard'";
		$id  = $wpdb->get_col( $wpdb->prepare( $sql ) );
		return $id;
	}

	private function get_value_relationship( $table, $value, $where, $value2 ) {
		global $wpdb;
		$sql    = "SELECT `{$value}`  FROM `{$wpdb->prefix}{$table}` WHERE `{$where}`=%s";
		$values = $wpdb->get_var( $wpdb->prepare( $sql, $value2 ) );
		return $values;
	}

	private function get_value_associations( $table, $value, $where, $value2 ) {
		global $wpdb;
		$sql    = "SELECT `{$value}`  FROM `{$wpdb->prefix}{$table}` WHERE `{$where}`=%s";
		$values = $wpdb->get_col( $wpdb->prepare( $sql, $value2 ) );
		return $values;
	}

	private function create_post( $id ) {
		$title  = $this->get_value_relationship( 'toolset_relationships', 'display_name_plural', 'id', $id );
		$status = $this->get_value_relationship( 'toolset_relationships', 'is_active', 'id', $id );
		$slug   = $this->get_value_relationship( 'toolset_relationships', 'slug', 'id', $id );
		$data   = [
			'post_title'  => $title,
			'post_type'   => 'mb-relationship',
			'post_status' => ( $status == '1' ) ? 'publish' : 'draft',
			'post_name'   => $slug
		];

		$relationship_id = $this->get_value_relationship( 'posts', 'ID', 'post_name', $slug );
		if ( $relationship_id ) {
			$data['ID'] = $relationship_id;
			wp_update_post( $data );
		} else {
			$relationship_id = wp_insert_post( $data );
		}
		return $relationship_id;
	}

	private function disable_post( $id ) {
		global $wpdb;
		$sql = "UPDATE `{$wpdb->prefix}toolset_relationships` SET is_active='0' WHERE id=%d";
		$wpdb->query( $wpdb->prepare( $sql, $id ) );
	}

	private function migrate_settings( $id, $relationship_id ) {
		$title        = $this->get_value_relationship( 'toolset_relationships', 'display_name_plural', 'id', $id );
		$slug         = $this->get_value_relationship( 'toolset_relationships', 'slug', 'id', $id );
		$parent       = $this->get_value_relationship( 'toolset_relationships', 'parent_types', 'id', $id );
		$parent_type  = $this->get_value_relationship( 'toolset_type_sets', 'type', 'set_id', $parent );
		$child        = $this->get_value_relationship( 'toolset_relationships', 'child_types', 'id', $id );
		$child_type   = $this->get_value_relationship( 'toolset_type_sets', 'type', 'set_id', $child );
		$relationship = [
			'id'         => $slug,
			'menu_title' => $title,
			'from'       => $parent_type,
			'to'         => $child_type,
		];
		$settings    = [
			'id'         => $slug,
			'menu_title' => $title,
			'from'       => [
				'object_type' => 'post',
				'post_type'=> $parent_type,
				'taxonomy' => 'category',
			],
			'to'         => [
				'object_type' => 'post',
				'post_type'=> $child_type,
				'taxonomy' => 'category',
			],
		];
		update_post_meta( $relationship_id, 'relationship', $relationship );
		update_post_meta( $relationship_id, 'settings', $settings );
	}

	private function migrate_values( $id ) {
		$parent_id      = $this->get_value_associations( 'toolset_associations', 'parent_id', 'relationship_id', $id );
		$element_parent = [];
		foreach( $parent_id as $value ) {
			$element_parent[] = $this->get_value_relationship( 'toolset_connected_elements', 'element_id', 'group_id', $value );
		}
		$child_id      = $this->get_value_associations( 'toolset_associations', 'child_id', 'relationship_id', $id );
		$element_child = [];
		foreach( $child_id as $value ) {
			$element_child[] = $this->get_value_relationship( 'toolset_connected_elements', 'element_id', 'group_id', $value );
		}
		$slug = $this->get_value_relationship( 'toolset_relationships', 'slug', 'id', $id );
		global $wpdb;
		$sql  = "INSERT INTO `{$wpdb->prefix}mb_relationships` ( `from`, `to`, `type` ) VALUES ( %d, %d, %s)";
		$from = $this->get_value_associations( 'mb_relationships', 'from', 'type', $slug );
		foreach( $element_parent as $key => $value ) {
			if ( !in_array( $value, $from ) ) {
				$wpdb->query( $wpdb->prepare( $sql, (int)$value, (int)$element_child[$key], $slug ) );
			}
		}
	}

	protected function migrate_item() {
		$this->migrate_relationship();
	}

	private function migrate_relationship() {
		$data_rs = $this->get_items();
		foreach ( $data_rs as $id ) {
			$relationship_id = $this->create_post( $id );
			$this->migrate_settings( $id, $relationship_id );
			$this->migrate_values( $id );
			$this->disable_post( $id );
		}
		wp_send_json_success( [
			'message' => __( 'Done', 'mb-toolset-migration' ),
			'type'    => 'done',
		] );
	}

}
