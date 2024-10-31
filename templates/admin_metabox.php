<?php
/**
 * Render the content of the metabox in the admin post editing form.
 */
$post = $GLOBALS['post'];
$post_id = $post->ID;
$parent_id = get_post_meta( $post_id, '_persistfork-parent', true );
$families = wp_get_object_terms( $post_id, 'family' );
$family = reset( $families );
if ( $parent_id ): ?>
	Parent:
	<a href="<?php echo get_permalink($parent_id) ?>">
		<?php echo get_post( $parent_id )->post_title ?>
	</a>
	<br />
	<?php if ( $family ): ?>
		Family:
		<a href="<?php echo home_url() . '/' . 'index.php/family/' . $family->slug . '/' ?>">
			<?php echo $family->name ?>
		</a>
	<?php else: ?>
		You started a new fork.
		<?php if ( current_user_can( 'delete_posts' ) ): ?>
			(<a href="<?php echo get_delete_post_link( $post_id ) ?>">undo</a>)
		<?php endif ?>
	<?php endif ?>
<?php else: ?>
	No parent
	<?php if ( $family ): ?>
		(root of family)
	<?php else: ?>
		(not a fork)
	<?php endif ?>
<?php endif ?>
