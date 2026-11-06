<?php
/**
 * The Writing desk. Only reachable by signed-in editors (see functions.php), and
 * the only page that carries a REST nonce, so public pages stay cacheable.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

get_header();
$naturedex_config = array(
	'root'     => esc_url_raw( rest_url( 'naturedex/v1/' ) ),
	'nonce'    => wp_create_nonce( 'wp_rest' ),
	'nonceUrl' => admin_url( 'admin-ajax.php?action=rest-nonce' ),
);
?>
<div id="writing-desk">
	<script type="application/json" id="naturedex-desk-config"><?php echo wp_json_encode( $naturedex_config, JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
	<noscript><p class="error-message">The writing desk needs JavaScript. You can also write in <a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">WordPress</a>.</p></noscript>
	<div class="section-toolbar">
		<p>Write a little. Share a discovery.</p>
		<div class="desk-account">
			<a class="text-link" href="<?php echo esc_url( admin_url() ); ?>">WordPress admin</a>
			<a class="text-link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sign out</a>
			<button class="primary-button" type="button" data-desk-new><span aria-hidden="true">＋</span> New post</button>
		</div>
	</div>
	<p class="status-message" role="status" data-desk-message></p>
	<p class="error-message" role="alert" data-desk-error hidden></p>

	<form class="editor-form panel" data-desk-form hidden>
		<div class="editor-heading">
			<h2 data-desk-form-title>A fresh page.</h2>
			<button type="button" class="text-link" data-desk-cancel>Cancel editing</button>
		</div>
		<label>Title <input name="title" required maxlength="160"></label>
		<div class="form-row">
			<label>
				<span id="post-slug-label">Slug</span>
				<input name="slug" aria-labelledby="post-slug-label" aria-describedby="post-slug-help" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="180">
				<small id="post-slug-help">Lowercase words separated by hyphens.</small>
			</label>
			<label>Category <input name="category" required maxlength="80"></label>
		</div>
		<label>Excerpt <textarea name="excerpt" required maxlength="500" rows="2"></textarea></label>
		<div class="body-label">
			<label for="post-body">Body</label>
			<button type="button" class="text-link" data-desk-preview-toggle aria-pressed="false">Preview article</button>
		</div>
		<section class="editor-preview" aria-label="Article preview" data-desk-preview hidden></section>
		<textarea id="post-body" name="body" required rows="13" maxlength="50000"></textarea>
		<p class="fine-print" data-desk-format-help>Separate paragraphs with a blank line. Use ## for section headings. Text is rendered safely; HTML is not executed.</p>
		<p class="fine-print" data-desk-rich-note hidden>
			This field note uses block editor content, so its body can’t be edited here. Other fields still save normally.
			<a data-desk-rich-link href="#">Edit the body in WordPress</a>
		</p>
		<div class="form-row">
			<label>
				Status
				<select name="status">
					<option value="draft">Draft</option>
					<option value="published">Published</option>
				</select>
			</label>
		</div>
		<fieldset class="related-picker">
			<legend>Related animals</legend>
			<div class="related-options">
				<?php foreach ( naturedex_species() as $naturedex_option ) : ?>
					<label><input type="checkbox" name="relatedSpeciesIds" value="<?php echo esc_attr( $naturedex_option['id'] ); ?>"> <?php echo esc_html( $naturedex_option['name'] ); ?></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<div class="editor-actions">
			<p class="fine-print">Saved to WordPress, with revision history.</p>
			<button type="submit" class="primary-button" data-desk-save><span data-desk-save-label>Save post</span> <?php naturedex_icon( 'check', 16 ); ?></button>
		</div>
	</form>

	<div class="editor-list" data-desk-list></div>
	<div class="empty-state" data-desk-empty hidden>
		<?php naturedex_icon( 'pen', 36 ); ?>
		<h2>Your journal starts here.</h2>
		<p>Create your first field note, save a draft, and publish when you’re ready.</p>
	</div>

	<template data-desk-row-icon><?php naturedex_icon( 'pen', 16 ); ?></template>
</div>
<?php
get_footer();
