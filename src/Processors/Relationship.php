<?php
namespace MetaBox\TS\Processors;

class Relationship extends Base {
	protected function get_items() {
		global $wpdb;
		$sql = "SELECT id FROM `{$wpdb->prefix}toolset_relationships` WHERE origin='wizard'";
		return $wpdb->get_col( $sql );
	}

	protected function migrate_item() {
		$items = $this->get_items();
		foreach ( $items as $id ) {
			$post_id = $this->create_post( $id );
			$this->migrate_settings( $id, $post_id );
			$this->migrate_values( $id );
			$this->disable_post( $id );
		}
		wp_send_json_success( [
			'message' => __( 'Done', 'mb-toolset-migration' ),
			'type'    => 'done',
		] );
	}

	private function create_post( $id ) {
		$title  = $this->get_col_single_value( 'toolset_relationships', 'display_name_plural', 'id', $id );
		$status = $this->get_col_single_value( 'toolset_relationships', 'is_active', 'id', $id );
		$slug   = $this->get_col_single_value( 'toolset_relationships', 'slug', 'id', $id );
		$data   = [
			'post_title'  => $title,
			'post_type'   => 'mb-relationship',
			'post_status' => $status == '1' ? 'publish' : 'draft',
			'post_name'   => $slug
		];

		$post_id = $this->get_col_single_value( 'posts', 'ID', 'post_name', $slug );
		if ( $post_id ) {
			$data['ID'] = $post_id;
			wp_update_post( $data );
		} else {
			$post_id = wp_insert_post( $data );
		}
		return $post_id;
	}

	private function disable_post( $id ) {
		global $wpdb;
		$sql = "UPDATE `{$wpdb->prefix}toolset_relationships` SET is_active='0' WHERE id=%d";
		$wpdb->query( $wpdb->prepare( $sql, $id ) );
	}

	private function migrate_settings( $id, $post_id ) {
		$title        = $this->get_col_single_value( 'toolset_relationships', 'display_name_plural', 'id', $id );
		$slug         = $this->get_col_single_value( 'toolset_relationships', 'slug', 'id', $id );
		$from_post    = $this->get_col_single_value( 'toolset_relationships', 'parent_types', 'id', $id );
		$from_type    = $this->get_col_single_value( 'toolset_type_sets', 'type', 'set_id', $from_post );
		$to_post      = $this->get_col_single_value( 'toolset_relationships', 'child_types', 'id', $id );
		$to_type      = $this->get_col_single_value( 'toolset_type_sets', 'type', 'set_id', $to_post );
		$relationship = [
			'id'         => $slug,
			'menu_title' => $title,
			'from'       => $from_type,
			'to'         => $to_type,
		];
		$settings    = [
			'id'         => $slug,
			'menu_title' => $title,
			'from'       => [
				'object_type' => 'post',
				'post_type'   => $from_type,
				'taxonomy'    => 'category',
			],
			'to'         => [
				'object_type' => 'post',
				'post_type'   => $to_type,
				'taxonomy'    => 'category',
			],
		];
		update_post_meta( $post_id, 'relationship', $relationship );
		update_post_meta( $post_id, 'settings', $settings );
	}

	private function migrate_values( $id ) {
		$parent_id      = $this->get_col_values( 'toolset_associations', 'parent_id', 'relationship_id', $id );
		$element_parent = [];
		foreach( $parent_id as $value ) {
			$element_parent[] = $this->get_col_single_value( 'toolset_connected_elements', 'element_id', 'group_id', $value );
		}
		$child_id      = $this->get_col_values( 'toolset_associations', 'child_id', 'relationship_id', $id );
		$element_child = [];
		foreach( $child_id as $value ) {
			$element_child[] = $this->get_col_single_value( 'toolset_connected_elements', 'element_id', 'group_id', $value );
		}
		$slug = $this->get_col_single_value( 'toolset_relationships', 'slug', 'id', $id );

		global $wpdb;
		$sql  = "INSERT INTO `{$wpdb->prefix}mb_relationships` ( `from`, `to`, `type` ) VALUES ( %d, %d, %s)";
		$from = $this->get_col_values( 'mb_relationships', 'from', 'type', $slug );

		foreach ( $element_parent as $key => $value ) {
			if ( !in_array( $value, $from ) ) {
				$wpdb->query( $wpdb->prepare( $sql, (int)$value, (int)$element_child[$key], $slug ) );
			}
		}
	}

	private function get_col_single_value( $table, $col, $conditional_col, $conditional_value ) {
		global $wpdb;
		$sql = "SELECT `{$col}` FROM `{$wpdb->prefix}{$table}` WHERE `{$conditional_col}`=%s LIMIT 1";
		return $wpdb->get_var( $wpdb->prepare( $sql, $conditional_value ) );
	}

	private function get_col_values( $table, $col, $conditional_col, $conditional_value ) {
		global $wpdb;
		$sql = "SELECT `{$col}`  FROM `{$wpdb->prefix}{$table}` WHERE `{$conditional_col}`=%s";
		return $wpdb->get_col( $wpdb->prepare( $sql, $conditional_value ) );
	}
}
