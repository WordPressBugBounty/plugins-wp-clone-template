<?php
/**
 * Appearance > Export screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpct_themes = wpct_get_themes();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Export Themes', 'wp-clone-template' ); ?></h1>

	<?php settings_errors( 'wpct' ); ?>

	<p><?php esc_html_e( 'Pick a theme to download it as a .zip file. You can install that file on another site through Appearance > Themes > Add New > Upload Theme.', 'wp-clone-template' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'wpct_export' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpct-template"><?php esc_html_e( 'Theme to export', 'wp-clone-template' ); ?></label>
				</th>
				<td>
					<select name="Templates" id="wpct-template" class="regular-text">
						<?php foreach ( $wpct_themes as $wpct_stylesheet => $wpct_name ) : ?>
							<option value="<?php echo esc_attr( $wpct_stylesheet ); ?>">
								<?php echo esc_html( $wpct_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Export', 'wp-clone-template' ), 'primary', 'export_template' ); ?>
	</form>
</div>
