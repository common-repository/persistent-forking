<?php
/**
 * Render the inset with fork button and parent link for the
 * public view of a post.
 *
 * Required parameters:
 * $post_id		the ID of the post for which the inset will be
 *				rendered
 * $image_url	the URL of the fork icon image file
 */
$fork_url = add_query_arg( array(
	'action' => 'persistent_fork',
	'post' => $post_id,
	'nonce' => wp_create_nonce( 'persistent_forking' ),
), home_url() );
$parent_id = get_post_meta( $post_id, '_persistfork-parent', true );
$families = wp_get_object_terms( $post_id, 'family' );
$family = reset( $families );
?>
<div id="persistfork-inset">
	<img src="<?php echo $image_url ?>" title="Fork" alt="Fork" />
	<a href="<?php echo $fork_url ?>" title="Fork this post">Fork</a>
	<?php if ( $parent_id ): ?>
		Forked from:
		<a href="<?php echo get_permalink( $parent_id ) ?>">
			<?php echo get_post( $parent_id )->post_title ?>
		</a>
	<?php endif ?>
	<?php if ( $family ):
		$family_id = $family->term_id; ?>
		<a href="#" onclick="persistfork.visualise(data_<?php echo $family_id ?>); return false;">
			Show family
		</a>
		<?php
		// Henceforth: JSON for vis.js graph. See also visualisation.js.
		global $persistfork_rendered;
		if ( ! isset( $persistfork_rendered ) ) $persistfork_rendered = array();
		// Each family tree is represented only once.
		if ( ! array_key_exists( $family_id, $persistfork_rendered ) ):
			$persistfork_rendered[ $family_id ] = true;
			$nodes = get_objects_in_term( $family_id, 'family' );
			$edges = array();
			foreach ( $nodes as $id ) {
				$parent_id = get_post_meta( $id, '_persistfork-parent', true );
				if ( $parent_id ) {
					$edges[] = array(
						'from' => $parent_id,
						'to' => $id,
					);
				}
			}
			$current_node = reset( $nodes );
			$current_edge = reset( $edges ); ?>
			<script>
				var data_<?php echo $family_id ?> = {
					nodes: [
						<?php while ( false !== $current_node ): ?>
							{
								id: <?php echo $current_node ?>,
								label: '<?php
									echo esc_js( get_post( $current_node )->post_title )
								?>',
								href: '<?php echo get_permalink( $current_node ) ?>'
							}<?php
							$current_node = next( $nodes );
							if ( false !== $current_node ) {
								echo ',';
							}
						endwhile ?>
					],
					edges: [
						<?php while ( false !== $current_edge ): ?>
							{
								from: <?php echo $current_edge['from'] ?>,
								to: <?php echo $current_edge['to'] ?>
							}<?php
							$current_edge = next( $edges );
							if ( false !== $current_edge ) {
								echo ',';
							}
						endwhile ?>
					]
				};
			</script>
		<?php endif ?>
	<?php endif ?>
</div>
