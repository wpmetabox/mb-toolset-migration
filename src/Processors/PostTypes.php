<?php
namespace MetaBox\TS\Processors;

use MetaBox\Support\Arr;
use WP_Query;

class PostTypes extends Base {

	protected function get_items() {

		$data_cptts = get_option( 'wpcf-custom-types' );

		if ( empty( $data_cptts ) ) {
			return [];
		}

		$excepts = $this->get_except_post_types();
		if ( ! $excepts ) {
			return $data_cptts;
		}
		$excepts_pt = [];
		foreach ( $excepts as $except ) {
			$excepts_pt[] = $except->post_title;
		}

		$data_pt = [];
		foreach ( $data_cptts as $key => $post_type ) {
			if ( ! in_array( $key, $excepts_pt ) ) {
				$data_pt[$key] = $post_type;
			}
		}

		return $data_pt;
	}

	private function get_except_post_types() {
		global $wpdb;
		$sql        = "SELECT post_title FROM $wpdb->posts WHERE post_type=%s AND post_status=%s";
		$excepts_pt = $wpdb->get_results( $wpdb->prepare( $sql, 'wp-types-group', 'hidden' ) );
		return $excepts_pt;
	}

	protected function migrate_item() {
		$this->migrate_post_types();
	}

	private function migrate_post_types() {
		$data_cptts = $this->get_items();
		foreach ( $data_cptts as $value ) {
			$plural                 = Arr::get( $value, 'labels.name' );
			$singular               = Arr::get( $value, 'labels.singular_name' );
			$slug                   = Arr::get( $value, 'slug' );
			$value['menu_position'] = (int) Arr::get( $value, 'menu_position' ) ?: '';
			$value['archive_slug']  = Arr::get( $value, 'has_archive_slug' );
			$value['icon_type']     = 'dashicons';
			$value['icon']          = 'dashicons-'.Arr::get( $value, 'icon' ) ?: 'dashicons-admin-post';
			$value['hierarchical']  = Arr::get( $value, 'hierarchical' ) ? true : false;
			$supports               = Arr::get( $value, 'supports', [] );
			$taxonomies             = Arr::get( $value, 'taxonomies', [] );
			$value['supports']      = [];
			$value['taxonomies']    = [];
			foreach( $supports as $key => $values ) {
				$value['supports'][] = $key;
			}
			foreach( $taxonomies as $key => $values ) {
				$value['taxonomies'][] = $key;
			}

			$array = [
				'menu_name'                => Arr::get( $value, 'labels.menu_name', $plural ) ?: $plural,
				'all_items'                => Arr::get( $value, 'labels.all_items' ),
				'view_items'               => Arr::get( $value, 'labels.view_items', 'View '.$plural ) ?: 'View '.$plural,
				'search_items'             => sprintf( Arr::get( $value, 'labels.search_items' ), $plural ),
				'not_found'                => sprintf( Arr::get( $value, 'labels.not_found'), $plural ),
				'not_found_in_trash'       => sprintf( Arr::get( $value, 'labels.not_found_in_trash' ), $plural ),
				'add_new_item'             => sprintf( Arr::get( $value, 'labels.add_new_item' ), $singular ),
				'edit_item'                => sprintf( Arr::get( $value, 'labels.edit_item' ), $singular ),
				'new_item'                 => sprintf( Arr::get( $value, 'labels.new_item' ), $singular ),
				'view_item'                => sprintf( Arr::get( $value, 'labels.view_item' ), $singular ),
				'add_new'                  => Arr::get( $value, 'labels.add_new' ) ,
				'parent_item_colon'        => Arr::get( $value, 'labels.parent_item_colon' ),
				'featured_image'           => Arr::get( $value, 'labels.featured_image', 'Featured image' ) ?: 'Featured image',
				'set_featured_image'       => Arr::get( $value, 'labels.set_featured_image', 'Set featured image' ) ?: 'Set featured image',
				'remove_featured_image'    => Arr::get( $value, 'labels.remove_featured_image', 'Remove featured image' ) ?: 'Remove featured image',
				'use_featured_image'       => Arr::get( $value, 'labels.use_featured_image', 'Use as featured image' ) ?: 'Use as featured image',
				'archives'                 => Arr::get( $value, 'labels.archives', $singular.' archives' ) ?: $singular.' archives',
				'insert_into_item'         => Arr::get( $value, 'labels.insert_into_item', 'Insert into '.$singular ) ?: 'Insert into '.$singular,
				'uploaded_to_this_item'    => Arr::get( $value, 'labels.uploaded_to_this_item', 'Uploaded to this '.$singular ) ?: 'Uploaded to this '.$singular,
				'filter_items_list'        => Arr::get( $value, 'labels.filter_items_list', 'Filter '.$plural.' list' ) ?: 'Filter '.$plural.' list',
				'items_list_navigation'    => Arr::get( $value, 'labels.items_list_navigation', $plural.' list navigation' ) ?: $plural.' list navigation',
				'items_list'               => Arr::get( $value, 'labels.items_list', $plural.' list' ) ?: $plural.' list',
				'attributes'               => Arr::get( $value, 'labels.attributes', $plural.' attributes' ) ?: $plural.' attributes',
				'item_published'           => Arr::get( $value, 'labels.item_published', $singular.' published' ) ?: $singular.' published',
				'item_published_privately' => Arr::get( $value, 'labels.item_published_privately', $singular.' published privately' ) ?: $singular.' published privately',
				'item_reverted_to_draft'   => Arr::get( $value, 'labels.item_published_privately', $singular.' reverted to draft' ) ?: $singular.' reverted to draft',
				'item_scheduled'           => Arr::get( $value, 'labels.item_scheduled', $singular.' scheduled' ) ?: $singular.' scheduled',
				'item_updated'             => Arr::get( $value, 'labels.item_updated', $singular.' updated' ) ?: $singular.' updated',
			];
			$value['labels'] = array_merge( $value['labels'], $array );
			$content         = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
			$content         = str_replace( '"1"', 'true', $content );
			global $wpdb;
			$post_id         = $this->get_id_by_slug( $slug, 'mb-post-type' );

			if ( $post_id ) {
				wp_update_post([
					'ID'           => $post_id,
					'post_content' => $content,
				]);
			} else {
				wp_insert_post([
					'post_content' => $content,
					'post_type'    => 'mb-post-type',
					'post_title'   => $plural,
					'post_status'  => 'publish',
					'post_name'    => $slug,
				]);
			}
		}
		$data_cptts     = get_option( 'wpcf-custom-types' );
		$data_cptts_new = [];
		foreach ( $data_cptts as $key => $value ) {
			$value['disabled']       = '1' ;
			$data_cptts_new[ $key ]  = $value;
		}
		update_option( 'wpcf-custom-types', $data_cptts_new );
		wp_send_json_success( [
			'message' => __( 'Done', 'mb-toolset-migration' ),
			'type'    => 'done',
		] );
	}

}
