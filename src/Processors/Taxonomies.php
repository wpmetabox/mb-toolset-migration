<?php
namespace MetaBox\TS\Processors;

use MetaBox\Support\Arr;
use WP_Query;

class Taxonomies extends Base {

	protected function get_items() {

		$data_taxots = get_option( 'wpcf-custom-taxonomies' );

		if ( empty( $data_taxots ) ) {
			return [];
		}

		return $data_taxots;
	}

	protected function migrate_item() {
		$this->migrate_taxonomies();
	}

	private function migrate_taxonomies() {
		if ( session_status() !== PHP_SESSION_ACTIVE ) {
			session_start();
		}
		$data_taxots = $this->get_items();
		$i           = 0;
		foreach ( $data_taxots as $value ) {
			$i ++;
			if ( $i < 3 ) {
				continue;
			}
			$plural               = Arr::get( $value, 'labels.name' );
			$singular             = Arr::get( $value, 'labels.singular_name' );
			$supports             = Arr::get( $value, 'supports', [] );
			$value['query_var']   = Arr::get( $value, 'query_var_enabled' );
			$value['meta_box_cb'] = Arr::get( $value, 'meta_box_cb.callback', 'post_tags_meta_box' );
			$value['types']         = [];
			foreach( $supports as $key => $values ) {
				$value['types'][] = $key;
			}

			$array = [
				'menu_name'                  => sprintf( Arr::get( $value, 'labels.menu_name' ), $plural ),
				'search_items'               => sprintf( Arr::get( $value, 'labels.search_items' ), $plural ),
				'popular_items'              => sprintf( Arr::get( $value, 'labels.popular_items' ), $plural ),
				'all_items'                  => sprintf( Arr::get( $value, 'labels.all_items' ), $plural ),
				'parent_item'                => sprintf( Arr::get( $value, 'labels.parent_item' ), $singular ),
				'parent_item_colon'          => sprintf( Arr::get( $value, 'labels.parent_item_colon' ), $singular ),
				'edit_item'                  => sprintf( Arr::get( $value, 'labels.edit_item' ), $singular ),
				'update_item'                => sprintf( Arr::get( $value, 'labels.update_item' ), $singular ),
				'add_new_item'               => sprintf( Arr::get( $value, 'labels.add_new_item' ), $singular ),
				'new_item_name'              => sprintf( Arr::get( $value, 'labels.new_item_name' ), $singular ),
				'separate_items_with_commas' => sprintf( Arr::get( $value, 'labels.separate_items_with_commas' ), $plural ),
				'add_or_remove_items'        => sprintf( Arr::get( $value, 'labels.add_or_remove_items' ), $plural ),
				'choose_from_most_used'      => sprintf( Arr::get( $value, 'labels.choose_from_most_used' ), $plural ),
				'view_item'                  => Arr::get( $value, 'labels.view_item', 'View '.$singular ) ?: 'View '.$singular,
				'filter_by_item'             => Arr::get( $value, 'labels.filter_by_item', 'Filter by '.$singular ) ?: 'Filter by '.$singular,
				'not_found'                  => Arr::get( $value, 'labels.not_found', 'Not '.$plural.' found' ) ?: 'Not '.$plural.' found',
				'no_terms'                   => Arr::get( $value, 'labels.no_terms', 'No '.$plural ) ?: 'No '.$plural,
				'items_list_navigation'      => Arr::get( $value, 'labels.items_list_navigation', $plural.' list navigation' ) ?: $plural.' list navigation',
				'items_list'                 => Arr::get( $value, 'labels.items_list', $plural.' list' ) ?: $plural.' list',
				'back_to_items'              => Arr::get( $value, 'labels.back_to_items', 'Back to '.$plural ) ?: 'Back to '.$plural,
			];
			$value['labels'] = array_merge( $value['labels'], $array );
			$content         = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
			$content         = str_replace( '"1"', 'true', $content );
			wp_insert_post([
				'post_content' => $content,
				'post_type'    => 'mb-taxonomy',
				'post_title'   => $singular,
				'post_status'  => 'publish',
			]);
		}
		$data_taxots_new = [];
		$i               = 0;
		foreach ( $data_taxots as $key => $value ) {
			$i ++;
			if ( $i > 2 ) {
				$value['disabled']   = '1' ;
			}
			$data_taxots_new[ $key ] = $value;
		}
		update_option( 'wpcf-custom-taxonomies', $data_taxots_new );
		wp_send_json_success( [
			'message' => __( 'Done', 'mb-toolset-migration' ),
			'type'    => 'done',
		] );
	}

}