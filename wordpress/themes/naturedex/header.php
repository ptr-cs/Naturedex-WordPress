<?php
/**
 * Document head, sidebar, and top bar.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

$naturedex_view    = naturedex_view();
$naturedex_heading = naturedex_heading();
$naturedex_total   = count( naturedex_species() );
$naturedex_nav     = array(
	array( 'Field guide', '/', 'book', array( 'guide', 'species' ) ),
	array( 'Region map', '/map', 'map', array( 'map' ) ),
	array( 'Field journal', '/journal', 'pen', array( 'journal', 'article' ) ),
	array( 'Writing desk', '/editor', 'leaf', array( 'editor' ) ),
);
?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script>document.documentElement.className = 'js';</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to main content</a>
<div class="app-shell">
	<aside class="sidebar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand" aria-label="Naturedex home">
			<span class="brand-mark" aria-hidden="true">
				<svg viewBox="0 0 32 32"><path d="M5 15h6V9h6V5h11v11h-5v6h-7v6h-5v-7H5z" fill="currentColor"/><path d="M12 19h5v-5h6" fill="none" stroke="#f4f2e6" stroke-width="3"/></svg>
			</span>
			<span>naturedex<span class="brand-period">.</span></span>
		</a>
		<p class="edition-label">VIRGINIA EDITION <span>01</span></p>
		<div class="sidebar-rule"></div>
		<span class="nav-label">YOUR FIELD STATION</span>
		<nav aria-label="Main navigation">
			<?php foreach ( $naturedex_nav as [ $naturedex_label, $naturedex_path, $naturedex_icon_name, $naturedex_views ] ) : ?>
				<?php $naturedex_active = in_array( $naturedex_view, $naturedex_views, true ); ?>
				<a href="<?php echo esc_url( home_url( $naturedex_path ) ); ?>" class="nav-item<?php echo $naturedex_active ? ' active' : ''; ?>"<?php echo $naturedex_active ? ' aria-current="page"' : ''; ?>>
					<?php naturedex_icon( $naturedex_icon_name ); ?>
					<span><?php echo esc_html( $naturedex_label ); ?></span>
					<?php if ( '/' === $naturedex_path ) : ?>
						<span class="nav-count"><?php echo (int) $naturedex_total; ?></span>
					<?php elseif ( '/editor' === $naturedex_path ) : ?>
						<span class="nav-local">EDITORS</span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="sidebar-collection js-only">
			<span class="eyebrow">YOUR COLLECTION</span>
			<div class="collection-count">
				<span data-collection-count>00</span>
				<small>/ <?php echo (int) $naturedex_total; ?> discovered</small>
			</div>
			<div class="progress-track"><span data-collection-progress style="width: 0%"></span></div>
			<a href="<?php echo esc_url( home_url( '/?collection=1' ) ); ?>" class="collection-link">
				View your discoveries <?php naturedex_icon( 'arrow', 14 ); ?>
			</a>
		</div>
		<div class="sidebar-bottom">
			<div class="little-grass" aria-hidden="true">♧ ♧ ♧</div>
			<p>A little curiosity.<br>A whole wild world.</p>
			<a href="<?php echo esc_url( home_url( '/about' ) ); ?>"><?php naturedex_icon( 'info', 15 ); ?> About this field guide</a>
			<span class="local-indicator"><span></span>POWERED BY WORDPRESS</span>
		</div>
	</aside>
	<div class="workspace">
		<header class="topbar">
			<div class="breadcrumb">
				<span>EXPLORER’S LOG</span>
				<span aria-hidden="true">/</span>
				<strong><?php echo esc_html( $naturedex_heading['crumb'] ); ?></strong>
			</div>
			<div class="topbar-right">
				<button class="sprite-motion-toggle js-only" type="button" data-motion-toggle disabled aria-pressed="false">
					<svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path data-motion-icon d="M3 1v10l7-5z"/></svg>
					<span data-motion-label>Animate sprites</span>
				</button>
				<span id="sprite-motion-explanation" class="sr-only" hidden>Sprite animations are off because reduced motion is enabled in your device settings.</span>
				<span class="local-badge"><span></span>VIRGINIA EDITION</span>
				<span class="researcher-avatar" aria-hidden="true">N</span>
			</div>
		</header>
		<main id="main" tabindex="-1">
			<div class="page-heading">
				<div>
					<p class="eyebrow"><span class="tiny-cross" aria-hidden="true">✦</span> <?php echo esc_html( $naturedex_heading['eyebrow'] ); ?></p>
					<h1><?php echo esc_html( $naturedex_heading['title'] ); ?></h1>
					<?php if ( $naturedex_heading['description'] ) : ?>
						<p class="page-description"><?php echo esc_html( $naturedex_heading['description'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $naturedex_heading['stamp'] ) : ?>
					<div class="heading-stamp">
						<?php naturedex_icon( $naturedex_heading['stamp'], 22 ); ?>
						<span>EST. 2026<br><strong>STAY CURIOUS</strong></span>
					</div>
				<?php endif; ?>
			</div>
			<p class="status-message" role="status" data-site-notice></p>
