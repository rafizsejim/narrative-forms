<?php
/**
 * AI form builder (bring your own key).
 *
 * Adds a "Generate with AI" tool to core's form-edit toolbar. The site owner
 * supplies their own API key for an OpenAI-compatible provider (DeepSeek,
 * OpenAI, OpenRouter, or a custom endpoint) under Settings. The browser calls a
 * WP AJAX endpoint; the server forwards the prompt + current form HTML to the
 * provider and returns clean Narrative Forms HTML, which replaces the editor
 * content. Core's kses allowlist sanitizes it on Save like any other markup.
 *
 * The key is stored admin-only, never sent to the browser, and can be supplied
 * via the NRFM_AI_API_KEY constant or the nrfm_ai_api_key filter instead of the DB.
 *
 * @package Narrative_Forms
 */

defined( 'ABSPATH' ) || exit;

class NRFM_AI_Form_Builder {

	public function __construct() {
		add_action( 'nrfm_field_buttons_after', array( $this, 'render_button' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_ajax_nrfm_ai_generate', array( $this, 'ajax_generate' ) );
		add_action( 'nrfm_settings_api_integrations', array( $this, 'render_settings' ) );
		add_filter( 'nrfm_save_global_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Built-in OpenAI-compatible providers: label + chat-completions endpoint +
	 * a sensible default model. `custom` resolves its endpoint from the stored
	 * base URL. All share one request path (OpenAI chat-completions shape).
	 */
	public static function providers() {
		return array(
			'deepseek'   => array(
				'label' => 'DeepSeek',
				'url'   => 'https://api.deepseek.com/chat/completions',
				'model' => 'deepseek-chat',
			),
			'openai'     => array(
				'label' => 'OpenAI',
				'url'   => 'https://api.openai.com/v1/chat/completions',
				'model' => 'gpt-4o-mini',
			),
			'openrouter' => array(
				'label' => 'OpenRouter',
				'url'   => 'https://openrouter.ai/api/v1/chat/completions',
				'model' => 'openai/gpt-4o-mini',
			),
			'custom'     => array(
				'label' => __( 'Custom (OpenAI-compatible)', 'narrative-forms' ),
				'url'   => '',
				'model' => '',
			),
		);
	}

	private function get_provider() {
		$settings = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : array();
		$provider = isset( $settings['ai_provider'] ) ? (string) $settings['ai_provider'] : 'deepseek';
		$providers = self::providers();
		return isset( $providers[ $provider ] ) ? $provider : 'deepseek';
	}

	/**
	 * The API key: constant and filter override the stored value so it can be
	 * kept out of the database entirely.
	 */
	private function get_api_key() {
		$key = '';
		if ( defined( 'NRFM_AI_API_KEY' ) && NRFM_AI_API_KEY ) {
			$key = (string) NRFM_AI_API_KEY;
		} else {
			$settings = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : array();
			$key = isset( $settings['ai_api_key'] ) ? (string) $settings['ai_api_key'] : '';
		}
		return (string) apply_filters( 'nrfm_ai_api_key', $key );
	}

	private function key_is_from_constant() {
		return defined( 'NRFM_AI_API_KEY' ) && NRFM_AI_API_KEY;
	}

	/** Resolve the chat-completions endpoint for the active provider. */
	private function get_endpoint() {
		$provider  = $this->get_provider();
		$providers = self::providers();
		if ( $provider !== 'custom' ) {
			return $providers[ $provider ]['url'];
		}
		$settings = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : array();
		$base = isset( $settings['ai_base_url'] ) ? trim( (string) $settings['ai_base_url'] ) : '';
		$base = untrailingslashit( $base );
		if ( $base === '' ) {
			return '';
		}
		// Accept a full completions URL, a "/v1" base, or a bare host.
		if ( substr( $base, -16 ) === '/chat/completions' ) {
			$endpoint = $base;
		} elseif ( substr( $base, -3 ) === '/v1' ) {
			$endpoint = $base . '/chat/completions';
		} else {
			$endpoint = $base . '/v1/chat/completions';
		}
		return esc_url_raw( $endpoint );
	}

	private function get_model() {
		$settings  = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : array();
		$model     = isset( $settings['ai_model'] ) ? trim( (string) $settings['ai_model'] ) : '';
		if ( $model !== '' ) {
			return $model;
		}
		$providers = self::providers();
		$provider  = $this->get_provider();
		return $providers[ $provider ]['model'];
	}

	private function is_configured() {
		return $this->get_api_key() !== '' && $this->get_endpoint() !== '' && $this->get_model() !== '';
	}

	/* -------------------------------------------------------------- */
	/* Settings UI (renders into core's "API & Integrations" section)  */
	/* -------------------------------------------------------------- */

	public function render_settings() {
		$settings   = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : array();
		$provider   = $this->get_provider();
		$model      = isset( $settings['ai_model'] ) ? (string) $settings['ai_model'] : '';
		$base_url   = isset( $settings['ai_base_url'] ) ? (string) $settings['ai_base_url'] : '';
		$has_db_key = ! empty( $settings['ai_api_key'] );
		$from_const = $this->key_is_from_constant();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'AI Form Builder', 'narrative-forms' ); ?></th>
			<td>
				<label for="nrfm-ai-provider"><?php esc_html_e( 'Provider', 'narrative-forms' ); ?></label><br>
				<select id="nrfm-ai-provider" name="nrfm_ai_provider">
					<?php foreach ( self::providers() as $key => $p ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>><?php echo esc_html( $p['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Use your own account with an OpenAI-compatible AI provider. The plugin sends your instruction and the current form markup to this provider only when you click Generate.', 'narrative-forms' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Generating a very complex form can take up to a minute. On some hosts you may need to allow more time; developers can raise it with the nrfm_ai_timeout filter.', 'narrative-forms' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="nrfm-ai-key"><?php esc_html_e( 'API Key', 'narrative-forms' ); ?></label></th>
			<td>
				<?php if ( $from_const ) : ?>
					<p class="description"><?php esc_html_e( 'The API key is set with the NRFM_AI_API_KEY constant, so it cannot be edited here.', 'narrative-forms' ); ?></p>
				<?php else : ?>
					<input type="password" id="nrfm-ai-key" name="nrfm_ai_api_key" class="regular-text" autocomplete="off" value="" placeholder="<?php echo $has_db_key ? esc_attr__( 'A key is saved. Leave blank to keep it.', 'narrative-forms' ) : esc_attr__( 'Paste your API key', 'narrative-forms' ); ?>">
					<?php if ( $has_db_key ) : ?>
						<label style="margin-left:8px;"><input type="checkbox" name="nrfm_ai_api_key_clear" value="1"> <?php esc_html_e( 'Remove saved key', 'narrative-forms' ); ?></label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Stored for admins only and never sent to the browser. For extra safety, define NRFM_AI_API_KEY in wp-config.php instead.', 'narrative-forms' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="nrfm-ai-model"><?php esc_html_e( 'Model', 'narrative-forms' ); ?></label></th>
			<td>
				<input type="text" id="nrfm-ai-model" name="nrfm_ai_model" class="regular-text" value="<?php echo esc_attr( $model ); ?>" placeholder="<?php echo esc_attr( self::providers()[ $provider ]['model'] ); ?>">
				<p class="description"><?php esc_html_e( 'Leave blank to use the provider default. Model names change over time, so if a request is rejected, check your provider\'s current model list and enter one here. Defaults: DeepSeek deepseek-chat, OpenAI gpt-4o-mini, OpenRouter openai/gpt-4o-mini.', 'narrative-forms' ); ?></p>
			</td>
		</tr>
		<tr class="nrfm-setting-children" data-parent-input="#nrfm-ai-provider" data-parent-value="custom">
			<th scope="row"><label for="nrfm-ai-base-url"><?php esc_html_e( 'Custom API Base URL', 'narrative-forms' ); ?></label></th>
			<td>
				<input type="url" id="nrfm-ai-base-url" name="nrfm_ai_base_url" class="regular-text code" value="<?php echo esc_attr( $base_url ); ?>" placeholder="https://your-endpoint.example/v1">
				<p class="description"><?php esc_html_e( 'Only used when Provider is set to Custom. Enter the base URL of any OpenAI-compatible chat completions API.', 'narrative-forms' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist AI settings. Hooked to nrfm_save_global_settings, which fires from
	 * core's settings save after its own nonce + capability check.
	 */
	public function save_settings( $settings ) {
		$providers = self::providers();
		$provider  = isset( $_POST['nrfm_ai_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_ai_provider'] ) ) : 'deepseek';
		$settings['ai_provider'] = isset( $providers[ $provider ] ) ? $provider : 'deepseek';

		$settings['ai_model']    = isset( $_POST['nrfm_ai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_ai_model'] ) ) : '';
		$settings['ai_base_url'] = isset( $_POST['nrfm_ai_base_url'] ) ? esc_url_raw( wp_unslash( $_POST['nrfm_ai_base_url'] ) ) : '';

		// API key: never clobber a saved key with a blank submit. A dedicated
		// checkbox clears it. Ignored entirely when the constant is in use.
		// Core rebuilds $settings from POST and replaces the whole option, so a
		// blank submit must re-read the stored key from the DB to preserve it.
		if ( ! $this->key_is_from_constant() ) {
			if ( ! empty( $_POST['nrfm_ai_api_key_clear'] ) ) {
				$settings['ai_api_key'] = '';
			} else {
				$posted_key = isset( $_POST['nrfm_ai_api_key'] ) ? trim( (string) wp_unslash( $_POST['nrfm_ai_api_key'] ) ) : '';
				if ( $posted_key !== '' ) {
					$settings['ai_api_key'] = sanitize_text_field( $posted_key );
				} else {
					$stored = get_option( 'nrfm_settings', array() );
					$settings['ai_api_key'] = isset( $stored['ai_api_key'] ) ? (string) $stored['ai_api_key'] : '';
				}
			}
		}
		return $settings;
	}

	/* -------------------------------------------------------------- */
	/* Toolbar button + panel                                          */
	/* -------------------------------------------------------------- */

	/**
	 * Render the "Generate with AI" button + the (hidden) refine panel, right
	 * under core's field-insert toolbar.
	 *
	 * @param NRFM_Form $form The form being edited.
	 */
	public function render_button( $form ) {
		if ( ! function_exists( 'nrfm_can_manage' ) || ! nrfm_can_manage() ) {
			return;
		}
		$settings_url = admin_url( 'admin.php?page=nrfm-forms-settings' );
		?>
		<div class="nrfm-ai-builder" id="nrfm-ai-builder">
			<button type="button" class="button nrfm-ai-toggle" id="nrfm-ai-toggle" aria-expanded="false" aria-controls="nrfm-ai-panel">
				<span class="nrfm-ai-spark" aria-hidden="true">&#10024;</span>
				<?php esc_html_e( 'Generate with AI', 'narrative-forms' ); ?>
			</button>

			<div class="nrfm-ai-panel" id="nrfm-ai-panel" hidden>
				<?php if ( ! $this->is_configured() ) : ?>
					<p class="nrfm-ai-locked">
						<?php
						printf(
							/* translators: %s: link to the Settings page. */
							esc_html__( 'Add your AI provider key to build forms with AI. %s', 'narrative-forms' ),
							'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Open Settings', 'narrative-forms' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>

				<div class="nrfm-ai-starters">
					<?php
					$nrfm_ai_starters = array(
						__( 'Contact form', 'narrative-forms' )   => __( 'A contact form with name, email, subject, and message.', 'narrative-forms' ),
						__( 'RSVP', 'narrative-forms' )           => __( 'An RSVP form with name, email, number of guests, and a yes or no attending choice.', 'narrative-forms' ),
						__( 'Survey', 'narrative-forms' )         => __( 'A short customer satisfaction survey with a 1 to 5 rating and a comments box.', 'narrative-forms' ),
						__( 'Quiz', 'narrative-forms' )           => __( 'A 5 question multiple choice quiz about a topic of your choice.', 'narrative-forms' ),
						__( 'Job application', 'narrative-forms' ) => __( 'A job application form with name, email, phone, position, resume upload, and a cover letter.', 'narrative-forms' ),
					);
					foreach ( $nrfm_ai_starters as $nrfm_ai_label => $nrfm_ai_prompt ) :
						?>
						<button type="button" class="button nrfm-ai-chip" data-prompt="<?php echo esc_attr( $nrfm_ai_prompt ); ?>"><?php echo esc_html( $nrfm_ai_label ); ?></button>
						<?php
					endforeach;
					?>
				</div>

				<div class="nrfm-ai-transcript" id="nrfm-ai-transcript" aria-live="polite"></div>

				<label class="screen-reader-text" for="nrfm-ai-instruction"><?php esc_html_e( 'Describe your form or what to change', 'narrative-forms' ); ?></label>
				<textarea id="nrfm-ai-instruction" class="nrfm-ai-instruction" rows="2" placeholder="<?php esc_attr_e( 'Describe your form, or tell the AI what to change…', 'narrative-forms' ); ?>"></textarea>

				<div class="nrfm-ai-actions">
					<button type="button" class="button button-primary nrfm-ai-generate" id="nrfm-ai-generate"><?php esc_html_e( 'Generate', 'narrative-forms' ); ?></button>
					<button type="button" class="button nrfm-ai-regenerate" id="nrfm-ai-regenerate" disabled><?php esc_html_e( '↻ Regenerate', 'narrative-forms' ); ?></button>
					<button type="button" class="button-link nrfm-ai-reset" id="nrfm-ai-reset"><?php esc_html_e( 'Reset', 'narrative-forms' ); ?></button>
					<span class="spinner nrfm-ai-spinner" id="nrfm-ai-spinner" aria-hidden="true"></span>
					<span class="nrfm-ai-status" id="nrfm-ai-status" role="status"></span>
				</div>

				<p class="description nrfm-ai-hint"><?php esc_html_e( 'The AI replaces the form code below. Press Ctrl/Cmd+Z to undo, or Revert to an earlier version above.', 'narrative-forms' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function enqueue_admin( $hook ) {
		if ( ! nrfm_is_form_edit_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'nrfm-ai-form-builder',
			NRFM_PLUGIN_URL . 'assets/css/ai-form-builder.css',
			array(),
			NRFM_VERSION
		);

		wp_enqueue_script(
			'nrfm-ai-form-builder',
			NRFM_PLUGIN_URL . 'assets/js/ai-form-builder-admin.js',
			array(),
			NRFM_VERSION,
			true
		);

		$nrfm_settings = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : array();
		$nrfm_wrapper  = ( ! empty( $nrfm_settings['wrapper_tag'] ) && preg_match( '/^[a-z]+$/', $nrfm_settings['wrapper_tag'] ) )
			? $nrfm_settings['wrapper_tag'] : 'p';

		wp_localize_script(
			'nrfm-ai-form-builder',
			'nrfmAI',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'nrfm_ai_generate' ),
				'wrapperTag'  => $nrfm_wrapper,
				'configured'  => $this->is_configured() ? 1 : 0,
				'settingsUrl' => admin_url( 'admin.php?page=nrfm-forms-settings' ),
				'formId'      => isset( $_GET['form'] ) ? intval( wp_unslash( $_GET['form'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'i18n'        => array(
					'emptyPrompt'  => __( 'Tell the AI what to build or change first.', 'narrative-forms' ),
					'genericError' => __( 'Something went wrong. Try again in a moment.', 'narrative-forms' ),
					'you'          => __( 'You', 'narrative-forms' ),
					'done'         => __( 'Updated the form code.', 'narrative-forms' ),
					'revert'       => __( 'Revert', 'narrative-forms' ),
					'reverted'     => __( 'Reverted to that version.', 'narrative-forms' ),
					'errors'       => array(
						'not_configured'  => __( 'Add your AI provider key under Settings to build forms with AI.', 'narrative-forms' ),
						'prompt_too_long' => __( 'That request is a bit long. Shorten it and try again.', 'narrative-forms' ),
						'timeout'         => __( 'The AI took too long to respond. Try a simpler request, or try again.', 'narrative-forms' ),
						'provider_error'  => __( 'The AI provider returned an error. Check your key and model, then try again.', 'narrative-forms' ),
						'empty_output'    => __( 'The AI did not return a form. Try rephrasing your request.', 'narrative-forms' ),
					),
					'statuses'     => array(
						__( 'Generating…', 'narrative-forms' ),
						__( 'Thinking through the fields…', 'narrative-forms' ),
						__( 'Writing the form…', 'narrative-forms' ),
						__( 'Almost there…', 'narrative-forms' ),
					),
				),
			)
		);
	}

	/* -------------------------------------------------------------- */
	/* Server-side generation                                          */
	/* -------------------------------------------------------------- */

	public function ajax_generate() {
		if ( ! function_exists( 'nrfm_can_manage' ) || ! nrfm_can_manage() ) {
			wp_send_json_error( array( 'error' => 'forbidden', 'message' => __( 'You do not have permission to do this.', 'narrative-forms' ) ), 403 );
		}
		check_ajax_referer( 'nrfm_ai_generate', 'nonce' );

		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 'error' => 'not_configured' ), 400 );
		}

		$instruction = isset( $_POST['instruction'] ) ? trim( (string) wp_unslash( $_POST['instruction'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- free text prompt, sanitized below.
		$instruction = sanitize_textarea_field( $instruction );
		if ( $instruction === '' ) {
			wp_send_json_error( array( 'error' => 'empty_prompt' ), 400 );
		}
		if ( strlen( $instruction ) > 8000 ) {
			wp_send_json_error( array( 'error' => 'prompt_too_long' ), 413 );
		}

		// The current form markup is context sent to the provider (JSON-encoded by
		// wp_remote_post); it is not stored or echoed as HTML here, so it is passed
		// through unsanitized apart from unslashing and a length cap.
		$current_html = isset( $_POST['current_html'] ) ? (string) wp_unslash( $_POST['current_html'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( strlen( $current_html ) > 40000 ) {
			$current_html = substr( $current_html, 0, 40000 );
		}

		$history = array();
		if ( isset( $_POST['history'] ) && is_array( $_POST['history'] ) ) {
			$raw_history = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['history'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$history = array_slice( array_values( array_filter( $raw_history, 'is_string' ) ), -6 );
		}

		$wrapper = isset( $_POST['wrapper'] ) ? sanitize_text_field( wp_unslash( $_POST['wrapper'] ) ) : 'p';
		if ( ! preg_match( '/^[a-z]+$/', $wrapper ) ) {
			$wrapper = 'p';
		}

		$timeout    = (int) apply_filters( 'nrfm_ai_timeout', 60 );
		$max_tokens = (int) apply_filters( 'nrfm_ai_max_tokens', 8000 );

		$response = wp_remote_post(
			$this->get_endpoint(),
			array(
				'timeout' => max( 10, $timeout ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->get_api_key(),
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $this->get_model(),
						'temperature' => 0.3,
						'max_tokens'  => max( 256, $max_tokens ),
						'messages'    => array(
							array( 'role' => 'system', 'content' => $this->system_prompt() ),
							array( 'role' => 'user', 'content' => $this->build_user_message( $instruction, $current_html, $history, $wrapper ) ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$msg  = $response->get_error_message();
			$code = ( stripos( $msg, 'timed out' ) !== false || stripos( $msg, 'timeout' ) !== false ) ? 'timeout' : 'provider_error';
			wp_send_json_error( array( 'error' => $code ), 502 );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			wp_send_json_error( array( 'error' => 'provider_error', 'status' => $status ), 502 );
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = '';
		if ( is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) ) {
			$content = (string) $data['choices'][0]['message']['content'];
		}
		$html = trim( $this->strip_fences( $content ) );
		if ( $html === '' ) {
			wp_send_json_error( array( 'error' => 'empty_output' ), 502 );
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Build the user message. PHP port of buildUserMessage() from the former relay.
	 */
	private function build_user_message( $instruction, $current_html, $history, $wrapper ) {
		$parts = array();
		$parts[] = 'Wrapper element for each field: <' . $wrapper . '>.';
		if ( ! empty( $history ) ) {
			$lines = array();
			foreach ( $history as $h ) {
				$lines[] = '- ' . $h;
			}
			$parts[] = "Earlier requests (most recent last):\n" . implode( "\n", $lines );
		}
		if ( trim( $current_html ) !== '' ) {
			$parts[] = "Current form HTML:\n" . trim( $current_html );
			$parts[] = "Apply this new request, keeping everything that still fits and returning the COMPLETE updated form:\n" . $instruction;
		} else {
			$parts[] = "Create a form for this request:\n" . $instruction;
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * The system prompt. Kept byte-identical across calls so providers with prompt
	 * caching can reuse it. PHP port of systemPrompt() from the former relay.
	 */
	private function system_prompt() {
		return implode(
			"\n",
			array(
				'You build and edit form HTML for the Narrative Forms WordPress plugin.',
				'You are given the current form HTML (possibly empty) and an instruction. Return the COMPLETE updated form that satisfies the instruction; if the current form is empty, create it from scratch.',
				'Output raw HTML only: no markdown, no code fences, no comments, no explanation. Never include <form> or </form> tags; the plugin adds the form wrapper.',
				'',
				'Field rules:',
				'- Wrap each field in the wrapper element named in the request (default <p>) unless your own layout needs different structure.',
				'- Every control has a unique id and a matching name in snake_case derived from its label (lowercase, words joined by underscores).',
				'- Associate each label with its control via for/id.',
				'- For required fields add the attribute required to the control and append to the label text: <span class="nrfm-required" aria-hidden="true">*</span>',
				'- text/email/url/number/date use <input type="...">. Multi-line uses <textarea rows="5">.',
				'- A single choice from a list uses <select> whose first option is <option value="">Select...</option>.',
				'- Multiple checkboxes or radios use a <fieldset> with a <legend>, and one <label><input type="checkbox|radio" name="..." value="..."> Text</label> per option.',
				'- File uploads use <input type="file">. Always include a <button type="submit"> at the end.',
				'',
				'Styling and behaviour (you MAY include CSS and JS, which the plugin saves and renders):',
				'- For a plain request, return clean semantic HTML and let the theme style it. Do not add CSS/JS that was not asked for.',
				'- When the user asks for a design, styling, or a particular look: wrap the whole form in <div class="nfai-form"> and add ONE <style> block whose every selector is scoped under .nfai-form, so nothing leaks into the rest of the page. Use modern, minimal CSS.',
				'- When the user asks for interactivity (character counter, show/hide a field, live validation hints, multi-step, a budget estimate): add ONE <script> block at the end. Wrap it in an IIFE, scope all queries to the form container, attach listeners defensively, declare no globals, and never depend on jQuery.',
				'- The <script> MUST be safe to embed inline in HTML and MUST be valid JavaScript:',
				'  * Never write the literal characters </script> anywhere inside it. If you need that text in a string, write it split as "<\/script>".',
				'  * Do not put raw HTML closing tags (like </div>) inside JavaScript strings, and do not use <!-- --> comments inside the script.',
				'  * Build DOM with document.createElement and textContent; avoid innerHTML that contains markup.',
				'  * Double-check the script parses as valid JavaScript before returning it.',
				'- Saving computed values: ONLY inputs that have a name attribute are stored with the submission and appear in the CSV export. Values the script shows only in <span>, <div>, or other display elements are NOT submitted. So for any calculated result that should be saved (totals, prices, an estimate, a score, a summary of the user\'s choices), also create a hidden field, for example <input type="hidden" name="estimated_budget">, and have the script keep its value updated whenever inputs change. When a choice\'s human-readable text or price matters in the saved data, include it (e.g. a hidden field listing the selected upgrades with their prices), since checkboxes and selects submit their value attribute, not their visible label.',
				'- Keep any CSS/JS minimal and self-contained.',
				'',
				'Return only the form HTML, optionally followed by the scoped <style> and/or <script>.',
			)
		);
	}

	/** Strip a leading/wrapping code fence if the model added one. */
	private function strip_fences( $s ) {
		if ( preg_match( '/```(?:[a-zA-Z]+)?\s*(.*?)```/s', (string) $s, $m ) ) {
			return $m[1];
		}
		return (string) $s;
	}
}
