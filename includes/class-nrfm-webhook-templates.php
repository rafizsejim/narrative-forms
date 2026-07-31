<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhook Payload Templates - PRO Feature
 * Allows custom JSON payload templates with {{field}} placeholders
 */
class NRFM_Webhook_Templates {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'nrfm_webhook_request_args', array( $this, 'process_custom_template' ), 5, 3 );
		add_filter( 'nrfm_webhook_request_args', array( $this, 'apply_custom_headers' ), 20, 3 );
		add_action( 'nrfm_save_form', array( $this, 'save_webhook_templates' ), 10, 2 );
	}

	/**
	 * Save webhook template field when form is saved
	 * Hooks after core save to add template data
	 */
	public function save_webhook_templates( $form_id, $data ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by core before this hook fires
		$raw_actions = isset( $_POST['actions'] ) ? wp_unslash( $_POST['actions'] ) : array();

		if ( empty( $raw_actions['webhook'] ) || ! is_array( $raw_actions['webhook'] ) ) {
			return;
		}

		// Get the form's current actions (just saved by core)
		$actions = get_post_meta( $form_id, '_nrfm_actions', true );
		if ( empty( $actions ) || ! is_array( $actions ) ) {
			return;
		}
		if ( empty( $actions['webhook'] ) ) {
			return;
		}

		$updated = false;
		foreach ( $raw_actions['webhook'] as $index => $webhook_action ) {
			if ( isset( $actions['webhook'][ $index ] ) ) {
				// Save template if format is 'template'
				if ( ! empty( $webhook_action['template'] ) && ( $webhook_action['format'] ?? '' ) === 'template' ) {
					$template = $webhook_action['template'];
					// Basic validation - check it looks like JSON
					$decoded = json_decode( $template );
					if ( json_last_error() === JSON_ERROR_NONE || strpos( $template, '{{' ) !== false ) {
						// Allow templates with placeholders even if not valid JSON yet
						$actions['webhook'][ $index ]['template'] = $template;
						$updated = true;
					}
				} elseif ( isset( $actions['webhook'][ $index ]['template'] ) ) {
					// Remove template if format changed away from 'template'
					unset( $actions['webhook'][ $index ]['template'] );
					$updated = true;
				}

				// Optional custom headers as JSON object.
				$headers_json = isset( $webhook_action['headers_json'] ) ? trim( (string) $webhook_action['headers_json'] ) : '';
				if ( $headers_json === '' ) {
					if ( isset( $actions['webhook'][ $index ]['headers'] ) ) {
						unset( $actions['webhook'][ $index ]['headers'] );
						$updated = true;
					}
				} else {
					$decoded_headers = json_decode( $headers_json, true );
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_headers ) ) {
						$sanitized_headers = array();
						foreach ( $decoded_headers as $header_key => $header_value ) {
							$key = sanitize_text_field( trim( (string) $header_key ) );
							if ( $key === '' || is_array( $header_value ) || is_object( $header_value ) ) {
								continue;
							}
							$sanitized_headers[ $key ] = sanitize_text_field( (string) $header_value );
						}

						$existing_headers = isset( $actions['webhook'][ $index ]['headers'] ) && is_array( $actions['webhook'][ $index ]['headers'] )
							? $actions['webhook'][ $index ]['headers']
							: array();

						if ( ! empty( $sanitized_headers ) ) {
							if ( $existing_headers !== $sanitized_headers ) {
								$actions['webhook'][ $index ]['headers'] = $sanitized_headers;
								$updated = true;
							}
						} elseif ( isset( $actions['webhook'][ $index ]['headers'] ) ) {
							unset( $actions['webhook'][ $index ]['headers'] );
							$updated = true;
						}
					}
				}
			}
		}

		// Re-save if we added/updated templates
		if ( $updated ) {
			update_post_meta( $form_id, '_nrfm_actions', $actions );
		}
	}

	/**
	 * Enqueue admin JS for the webhook template UI
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! nrfm_is_form_edit_screen( $hook ) ) {
			return;
		}

		wp_enqueue_script(
			'nrfm-webhook-templates',
			NRFM_PLUGIN_URL . 'assets/js/webhook-templates.js',
			array( 'jquery' ),
			NRFM_VERSION,
			true
		);

		// Get form field names for autocomplete hints
		$form_id = nrfm_admin_request( 'form', 0 );
		$field_names = array();
		$existing_templates = array();
		$existing_headers = array();

		if ( $form_id > 0 && class_exists( 'NRFM_Form' ) ) {
			$form = new NRFM_Form( $form_id );
			if ( $form->exists() ) {
				if ( method_exists( $form, 'get_field_names' ) ) {
					$field_names = $form->get_field_names();
				}
				// Get existing webhook templates from post meta
				$actions = get_post_meta( $form_id, '_nrfm_actions', true );
				if ( ! empty( $actions['webhook'] ) && is_array( $actions['webhook'] ) ) {
					foreach ( $actions['webhook'] as $index => $webhook ) {
						if ( ! empty( $webhook['template'] ) ) {
							$existing_templates[ $index ] = $webhook['template'];
						}
						if ( ! empty( $webhook['headers'] ) && is_array( $webhook['headers'] ) ) {
							$existing_headers[ $index ] = wp_json_encode(
								$webhook['headers'],
								JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
							);
						}
					}
				}
			}
		}

		// Add meta fields that are always available
		$meta_fields = array(
			'nrfm_form_name',
			'nrfm_form_id',
			'nrfm_timestamp',
			'nrfm_ip_address',
			'nrfm_user_agent',
			'nrfm_referrer_url',
		);

		wp_localize_script( 'nrfm-webhook-templates', 'nrfmWebhookTemplates', array(
			'formatLabel'        => __( 'Custom Template', 'narrative-forms' ),
			'templateLabel'      => __( 'Payload Template', 'narrative-forms' ),
			'templateHelp'       => __( 'Use {{field_name}} placeholders for form fields. Use {{all_fields}} for all fields as key-value pairs.', 'narrative-forms' ),
			'headersLabel'       => __( 'Request Headers (JSON)', 'narrative-forms' ),
			'headersHelp'        => __( 'Optional. JSON object of headers, e.g. {"Authorization":"Bearer ...","Accept":"application/json"}', 'narrative-forms' ),
			'headersValid'       => __( 'Headers JSON is valid.', 'narrative-forms' ),
			'headersInvalid'     => __( 'Invalid JSON. Please provide a JSON object, e.g. {"Accept":"application/json"}', 'narrative-forms' ),
			'fieldNames'         => $field_names,
			'metaFields'         => $meta_fields,
			'availableLabel'     => __( 'Available placeholders:', 'narrative-forms' ),
			'presetLabel'        => __( 'Load Preset:', 'narrative-forms' ),
			'presetDiscord'      => __( 'Discord', 'narrative-forms' ),
			'presetSlack'        => __( 'Slack', 'narrative-forms' ),
			'presetTeams'        => __( 'Microsoft Teams', 'narrative-forms' ),
			'presetGeneric'      => __( 'Generic JSON', 'narrative-forms' ),
			'presets'            => $this->get_presets(),
			'existingTemplates'  => $existing_templates,
			'existingHeaders'    => $existing_headers,
		) );
	}

	/**
	 * Get preset templates for popular services
	 */
	private function get_presets() {
		$json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		return array(
			'discord' => wp_json_encode( array(
				'content' => 'New form submission received!',
				'embeds'  => array(
					array(
						'title'  => 'Form Submission by {{nrfm_form_name}}',
						'color'  => 5814783,
						'fields' => '{{all_fields_embed}}',
						'footer' => array(
							'text' => 'Submitted at {{nrfm_timestamp}}',
						),
					),
				),
			), $json_flags ),

			'slack' => wp_json_encode( array(
				'text'   => 'New form submission received!',
				'blocks' => array(
					array(
						'type' => 'section',
						'text' => array(
							'type' => 'mrkdwn',
							'text' => '*New Form Submission*',
						),
					),
					array(
						'type'   => 'section',
						'fields' => '{{all_fields_slack}}',
					),
				),
			), $json_flags ),

			'teams' => wp_json_encode( array(
				'@type'      => 'MessageCard',
				'@context'   => 'http://schema.org/extensions',
				'themeColor' => '0076D7',
				'summary'    => 'New form submission',
				'sections'   => array(
					array(
						'activityTitle' => 'New Form Submission',
						'facts'         => '{{all_fields_teams}}',
						'markdown'      => true,
					),
				),
			), $json_flags ),

			'generic' => wp_json_encode( array(
				'event'     => 'form_submission',
				'form_name' => '{{nrfm_form_name}}',
				'form_id'   => '{{nrfm_form_id}}',
				'timestamp' => '{{nrfm_timestamp}}',
				'ip'        => '{{nrfm_ip_address}}',
				'data'      => '{{all_fields}}',
			), $json_flags ),
		);
	}

	/**
	 * Process custom template and replace placeholders
	 */
	public function process_custom_template( $args, $payload, $webhook_config ) {
		// Check if this webhook uses a custom template
		if ( empty( $webhook_config['format'] ) || $webhook_config['format'] !== 'template' ) {
			return $args;
		}

		if ( empty( $webhook_config['template'] ) ) {
			return $args;
		}

		$template = $webhook_config['template'];
		$payload  = $this->inject_form_placeholders( $payload, $webhook_config );

		// Process special placeholders first (before individual fields)
		$template = $this->process_special_placeholders( $template, $payload );

		// Process individual field placeholders
		$template = $this->process_field_placeholders( $template, $payload );

		// Set the processed template as the body
		$args['headers']['Content-Type'] = 'application/json';
		$args['body'] = $template;

		return $args;
	}

	/**
	 * Add form-level placeholders (name/id) for template usage.
	 */
	private function inject_form_placeholders( $payload, $webhook_config ) {
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$form_id = isset( $webhook_config['form_id'] ) ? intval( $webhook_config['form_id'] ) : 0;
		if ( $form_id > 0 ) {
			$payload['nrfm_form_id'] = (string) $form_id;
			$title = get_the_title( $form_id );
			if ( is_string( $title ) && $title !== '' ) {
				$payload['nrfm_form_name'] = $title;
			}
		}
		if ( empty( $payload['nrfm_form_name'] ) ) {
			$payload['nrfm_form_name'] = 'Form';
		}
		if ( empty( $payload['nrfm_form_id'] ) ) {
			$payload['nrfm_form_id'] = '';
		}
		return $payload;
	}

	/**
	 * Apply optional custom headers configured per webhook action.
	 */
	public function apply_custom_headers( $args, $payload, $webhook_config ) {
		if ( empty( $webhook_config['headers'] ) || ! is_array( $webhook_config['headers'] ) ) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		foreach ( $webhook_config['headers'] as $key => $value ) {
			$header_key = sanitize_text_field( trim( (string) $key ) );
			if ( $header_key === '' ) {
				continue;
			}
			$args['headers'][ $header_key ] = sanitize_text_field( (string) $value );
		}

		return $args;
	}

	/**
	 * Process special placeholders like {{all_fields}}, {{all_fields_embed}}, etc.
	 */
	private function process_special_placeholders( $template, $payload ) {
		// Filter out nrfm_ meta fields for "all fields" replacements
		$form_fields = array();
		foreach ( $payload as $key => $value ) {
			if ( strpos( $key, 'nrfm_' ) === 0 ) {
				continue;
			} else {
				$form_fields[ $key ] = $value;
			}
		}

		// {{all_fields}} - All form fields as JSON object
		if ( strpos( $template, '"{{all_fields}}"' ) !== false ) {
			$template = str_replace( '"{{all_fields}}"', wp_json_encode( $form_fields ), $template );
		}

		// {{all_fields_embed}} - Discord embed fields format
		if ( strpos( $template, '"{{all_fields_embed}}"' ) !== false ) {
			$embed_fields = array();
			foreach ( $form_fields as $key => $value ) {
				$embed_fields[] = array(
					'name'   => $this->format_field_name( $key ),
					'value'  => $this->stringify_value( $value ),
					'inline' => strlen( $this->stringify_value( $value ) ) < 30,
				);
			}
			$template = str_replace( '"{{all_fields_embed}}"', wp_json_encode( $embed_fields ), $template );
		}

		// {{all_fields_slack}} - Slack fields format
		if ( strpos( $template, '"{{all_fields_slack}}"' ) !== false ) {
			$slack_fields = array();
			foreach ( $form_fields as $key => $value ) {
				$slack_fields[] = array(
					'type' => 'mrkdwn',
					'text' => '*' . $this->format_field_name( $key ) . '*\n' . $this->stringify_value( $value ),
				);
			}
			$template = str_replace( '"{{all_fields_slack}}"', wp_json_encode( $slack_fields ), $template );
		}

		// {{all_fields_teams}} - Microsoft Teams facts format
		if ( strpos( $template, '"{{all_fields_teams}}"' ) !== false ) {
			$teams_facts = array();
			foreach ( $form_fields as $key => $value ) {
				$teams_facts[] = array(
					'name'  => $this->format_field_name( $key ),
					'value' => $this->stringify_value( $value ),
				);
			}
			$template = str_replace( '"{{all_fields_teams}}"', wp_json_encode( $teams_facts ), $template );
		}

		return $template;
	}

	/**
	 * Process individual field placeholders like {{name}}, {{email}}, etc.
	 */
	private function process_field_placeholders( $template, $payload ) {
		foreach ( $payload as $key => $value ) {
			$placeholder = '{{' . $key . '}}';
			if ( strpos( $template, $placeholder ) !== false ) {
				$string_value = $this->stringify_value( $value );
				// Check if it's inside quotes (string context) or standalone (needs quotes)
				$template = str_replace( $placeholder, $this->escape_json_string( $string_value ), $template );
			}
		}

		// Remove any remaining unmatched placeholders
		$template = preg_replace( '/\{\{[^}]+\}\}/', '', $template );

		return $template;
	}

	/**
	 * Convert array/value to string
	 */
	private function stringify_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', $value );
		}
		return (string) $value;
	}

	/**
	 * Format field name for display (convert snake_case to Title Case)
	 */
	private function format_field_name( $name ) {
		return ucwords( str_replace( array( '_', '-' ), ' ', $name ) );
	}

	/**
	 * Escape string for JSON context
	 */
	private function escape_json_string( $string ) {
		// Escape special JSON characters
		$string = str_replace( '\\', '\\\\', $string );
		$string = str_replace( '"', '\\"', $string );
		$string = str_replace( "\n", '\\n', $string );
		$string = str_replace( "\r", '\\r', $string );
		$string = str_replace( "\t", '\\t', $string );
		return $string;
	}
}
