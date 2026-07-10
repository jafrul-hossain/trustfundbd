<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Notes: renewal dates and recurring reminders (hosting renewal,
 * domain renewal, backup schedule, etc.), highlighted when upcoming.
 */
class CHP_Client_Notes {

	public function render_page() {
		$notes = get_option( 'chp_client_notes', array() );

		if ( CHP_Helpers::verify_post( 'chp_notes_save', 'chp_notes_nonce' ) ) {
			if ( isset( $_POST['add_note'] ) ) {
				$title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
				$date       = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
				$recurrence = sanitize_key( wp_unslash( $_POST['recurrence'] ?? 'one_time' ) );
				$note       = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
				if ( $title && $date ) {
					$notes[] = array(
						'id'         => wp_generate_uuid4(),
						'title'      => $title,
						'date'       => $date,
						'recurrence' => $recurrence,
						'note'       => $note,
					);
					update_option( 'chp_client_notes', $notes );
					CHP_Helpers::notice( __( 'Note added.', 'client-handover-pro' ) );
				}
			} elseif ( isset( $_POST['delete_note'] ) ) {
				$id    = sanitize_text_field( wp_unslash( $_POST['delete_note'] ) );
				$notes = array_values( array_filter( $notes, static function ( $n ) use ( $id ) {
					return $n['id'] !== $id;
				} ) );
				update_option( 'chp_client_notes', $notes );
				CHP_Helpers::notice( __( 'Note removed.', 'client-handover-pro' ) );
			}
		}

		usort( $notes, static function ( $a, $b ) {
			return strtotime( $a['date'] ) <=> strtotime( $b['date'] );
		} );

		?>
		<div class="wrap chp-wrap">
			<h1 class="chp-title"><?php esc_html_e( 'Client Notes', 'client-handover-pro' ); ?></h1>
			<p class="chp-subtitle"><?php esc_html_e( 'Renewal dates, backup schedules and other reminders.', 'client-handover-pro' ); ?></p>

			<div class="chp-card">
				<table class="chp-table">
					<tbody>
					<?php if ( empty( $notes ) ) : ?>
						<tr><td><?php esc_html_e( 'No notes yet.', 'client-handover-pro' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $notes as $n ) : ?>
						<?php
						$days_away = ( strtotime( $n['date'] ) - time() ) / DAY_IN_SECONDS;
						$upcoming  = $days_away >= 0 && $days_away <= 30;
						?>
						<tr class="<?php echo $upcoming ? 'chp-row--upcoming' : ''; ?>">
							<td class="chp-table__label"><?php echo esc_html( $n['title'] ); ?></td>
							<td class="chp-table__message">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $n['date'] ) ) ); ?>
								<?php if ( 'one_time' !== $n['recurrence'] ) : ?>
									<span class="chp-badge"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $n['recurrence'] ) ) ); ?></span>
								<?php endif; ?>
								<?php if ( $upcoming ) : ?>
									<span class="chp-badge chp-badge--warn"><?php esc_html_e( 'Upcoming', 'client-handover-pro' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="chp-table__action">
								<form method="post" style="display:inline">
									<?php wp_nonce_field( 'chp_notes_save', 'chp_notes_nonce' ); ?>
									<button type="submit" name="delete_note" value="<?php echo esc_attr( $n['id'] ); ?>" class="chp-link chp-link--danger"><?php esc_html_e( 'Delete', 'client-handover-pro' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="chp-card">
				<div class="chp-card__label"><?php esc_html_e( 'Add a Note', 'client-handover-pro' ); ?></div>
				<form method="post" class="chp-form">
					<?php wp_nonce_field( 'chp_notes_save', 'chp_notes_nonce' ); ?>
					<div class="chp-form-row">
						<label><?php esc_html_e( 'Title', 'client-handover-pro' ); ?></label>
						<input type="text" name="title" class="regular-text" placeholder="Hosting Renewal" required />
					</div>
					<div class="chp-form-row">
						<label><?php esc_html_e( 'Date', 'client-handover-pro' ); ?></label>
						<input type="date" name="date" required />
					</div>
					<div class="chp-form-row">
						<label><?php esc_html_e( 'Recurrence', 'client-handover-pro' ); ?></label>
						<select name="recurrence">
							<option value="one_time"><?php esc_html_e( 'One time', 'client-handover-pro' ); ?></option>
							<option value="weekly"><?php esc_html_e( 'Weekly', 'client-handover-pro' ); ?></option>
							<option value="monthly"><?php esc_html_e( 'Monthly', 'client-handover-pro' ); ?></option>
							<option value="yearly"><?php esc_html_e( 'Yearly', 'client-handover-pro' ); ?></option>
						</select>
					</div>
					<div class="chp-form-row">
						<label><?php esc_html_e( 'Note', 'client-handover-pro' ); ?></label>
						<textarea name="note" class="widefat" rows="2"></textarea>
					</div>
					<button type="submit" name="add_note" value="1" class="chp-btn chp-btn--primary"><?php esc_html_e( 'Add Note', 'client-handover-pro' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}
}
