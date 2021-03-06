<?php

class Jetpack_Sync_Module_Comments extends Jetpack_Sync_Module {

	public function name() {
		return 'comments';
	}

	public function get_object_by_id( $object_type, $id ) {
		$comment_id = intval( $id );
		if ( $object_type === 'comment' && $comment = get_comment( $comment_id ) ) {
			return $this->filter_comment( $comment );
		}

		return false;
	}

	public function init_listeners( $callable ) {
		add_action( 'wp_insert_comment', $callable, 10, 2 );
		add_action( 'deleted_comment', $callable );
		add_action( 'trashed_comment', $callable );
		add_action( 'spammed_comment', $callable );
		add_action( 'trashed_post_comments', $callable, 10, 2 );
		add_action( 'untrash_post_comments', $callable );
		add_action( 'comment_approved_to_unapproved', $callable );
		add_action( 'comment_unapproved_to_approved', $callable );
		add_action( 'jetpack_modified_comment_contents', $callable, 10, 2 );
		add_filter( 'wp_update_comment_data', array( $this, 'handle_comment_contents_modification' ), 10, 3 );

		// even though it's messy, we implement these hooks because
		// the edit_comment hook doesn't include the data
		// so this saves us a DB read for every comment event
		foreach ( array( '', 'trackback', 'pingback' ) as $comment_type ) {
			foreach ( array( 'unapproved', 'approved' ) as $comment_status ) {
				$comment_action_name = "comment_{$comment_status}_{$comment_type}";
				add_action( $comment_action_name, $callable, 10, 2 );
			}
		}

		// listen for meta changes
		$this->init_listeners_for_meta_type( 'comment', $callable );
		$this->init_meta_whitelist_handler( 'comment', array( $this, 'filter_meta' ) );
	}

	public function handle_comment_contents_modification( $new_comment, $old_comment, $new_comment_with_slashes ) {
		$content_fields = array(
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_content',
		);
		$changes = array();
		foreach ( $content_fields as $field ) {
			if ( $new_comment_with_slashes[$field] != $old_comment[$field] ) {
				$changes[$field] = array( $new_comment[$field], $old_comment[$field] );
			}
		}

		if ( ! empty( $changes ) ) {
			/**
			 * Signals to the sync listener that this comment's contents were modified and a sync action
			 * reflecting the change(s) to the content should be sent
			 *
			 * @since 4.9.0
			 *
			 * @param int $new_comment['comment_ID'] ID of comment whose content was modified
			 * @param mixed $changes Array of changed comment fields with before and after values
			 */
			do_action( 'jetpack_modified_comment_contents', $new_comment['comment_ID'], $changes );
		}
		return $new_comment;
	}

	public function init_full_sync_listeners( $callable ) {
		add_action( 'jetpack_full_sync_comments', $callable ); // also send comments meta
	}

	public function init_before_send() {
		add_filter( 'jetpack_sync_before_send_wp_insert_comment', array( $this, 'expand_wp_insert_comment' ) );

		foreach ( array( '', 'trackback', 'pingback' ) as $comment_type ) {
			foreach ( array( 'unapproved', 'approved' ) as $comment_status ) {
				$comment_action_name = "comment_{$comment_status}_{$comment_type}";
				add_filter( 'jetpack_sync_before_send_' . $comment_action_name, array(
					$this,
					'expand_wp_insert_comment',
				) );
			}
		}

		// full sync
		add_filter( 'jetpack_sync_before_send_jetpack_full_sync_comments', array( $this, 'expand_comment_ids' ) );
	}

	public function enqueue_full_sync_actions( $config, $max_items_to_enqueue, $state ) {
		global $wpdb;
		return $this->enqueue_all_ids_as_action( 'jetpack_full_sync_comments', $wpdb->comments, 'comment_ID', $this->get_where_sql( $config ), $max_items_to_enqueue, $state );
	}

	public function estimate_full_sync_actions( $config ) {
		global $wpdb;

		$query = "SELECT count(*) FROM $wpdb->comments";

		if ( $where_sql = $this->get_where_sql( $config ) ) {
			$query .= ' WHERE ' . $where_sql;
		}

		$count = $wpdb->get_var( $query );

		return (int) ceil( $count / self::ARRAY_CHUNK_SIZE );
	}

	private function get_where_sql( $config ) {
		if ( is_array( $config ) ) {
			return 'comment_ID IN (' . implode( ',', array_map( 'intval', $config ) ) . ')';
		}

		return null;
	}

	public function get_full_sync_actions() {
		return array( 'jetpack_full_sync_comments' );
	}

	public function count_full_sync_actions( $action_names ) {
		return $this->count_actions( $action_names, array( 'jetpack_full_sync_comments' ) );
	}

	function expand_wp_comment_status_change( $args ) {
		return array( $args[0], $this->filter_comment( $args[1] ) );
	}

	function expand_wp_insert_comment( $args ) {
		return array( $args[0], $this->filter_comment( $args[1] ) );
	}

	function filter_comment( $comment ) {
		/**
		 * Filters whether to prevent sending comment data to .com
		 *
		 * Passing true to the filter will prevent the comment data from being sent
		 * to the WordPress.com.
		 * Instead we pass data that will still enable us to do a checksum against the
		 * Jetpacks data but will prevent us from displaying the data on in the API as well as
		 * other services.
		 * @since 4.2.0
		 *
		 * @param boolean false prevent post data from bing synced to WordPress.com
		 * @param mixed $comment WP_COMMENT object
		 */
		if ( apply_filters( 'jetpack_sync_prevent_sending_comment_data', false, $comment ) ) {
			$blocked_comment                   = new stdClass();
			$blocked_comment->comment_ID       = $comment->comment_ID;
			$blocked_comment->comment_date     = $comment->comment_date;
			$blocked_comment->comment_date_gmt = $comment->comment_date_gmt;
			$blocked_comment->comment_approved = 'jetpack_sync_blocked';

			return $blocked_comment;
		}

		return $comment;
	}

	// Comment Meta
	function is_whitelisted_comment_meta( $meta_key ) {
		return in_array( $meta_key, Jetpack_Sync_Settings::get_setting( 'comment_meta_whitelist' ) );
	}

	function filter_meta( $args ) {
		return ( $this->is_whitelisted_comment_meta( $args[2] ) ? $args : false );
	}

	public function expand_comment_ids( $args ) {
		$comment_ids = $args[0];
		$comments    = get_comments( array(
			'include_unapproved' => true,
			'comment__in'        => $comment_ids,
		) );

		return array(
			$comments,
			$this->get_metadata( $comment_ids, 'comment', Jetpack_Sync_Settings::get_setting( 'comment_meta_whitelist' ) ),
		);
	}
}
