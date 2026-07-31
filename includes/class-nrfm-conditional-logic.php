<?php

defined( 'ABSPATH' ) || exit;

class NRFM_Conditional_Logic {
	private $rules_queue = array();

	public function __construct() {
		add_filter( 'nrfm_form_tabs', array( $this, 'add_tab' ), 10, 2 );
		add_action( 'nrfm_form_tab_content', array( $this, 'render_tab' ), 10, 2 );
		add_action( 'nrfm_save_form', array( $this, 'save_rules' ), 10, 2 );
		add_filter( 'nrfm_required_fields', array( $this, 'filter_required_fields' ), 10, 3 );
		add_filter( 'nrfm_form_render', array( $this, 'queue_rules_for_frontend' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'enqueue_frontend' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	public function add_tab( $tabs, $form_id ) {
		$tabs['conditional'] = esc_html__( 'Conditional Logic', 'narrative-forms' );
		return $tabs;
	}

	public function render_tab( $tab_key, $form ) {
		if ( $tab_key !== 'conditional' || ! ( $form instanceof NRFM_Form ) ) {
			return;
		}
		$form_id = $form->get_id();
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'fields';
		$is_active   = ( $current_tab === 'conditional' );
		$fields  = $form->get_field_names();
		$rules   = get_post_meta( $form_id, '_nrfm_conditions', true );
		$mode    = get_post_meta( $form_id, '_nrfm_conditions_mode', true );
		$mode    = in_array( $mode, array( 'all', 'any' ), true ) ? $mode : 'any';
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}
		?>
		<div class="nrfm-tab-panel <?php echo $is_active ? 'active' : ''; ?>" id="tab-conditional" style="padding-top:16px;">
			<h2><?php esc_html_e( 'Conditional Logic', 'narrative-forms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Show or hide a field when another field matches a rule.', 'narrative-forms' ); ?></p>
			<p style="margin:10px 0 0;">
				<label for="nrfm-conditions-mode"><strong><?php esc_html_e( 'Match', 'narrative-forms' ); ?></strong></label>
				<select id="nrfm-conditions-mode" name="nrfm_conditions_mode">
					<option value="any" <?php selected( $mode, 'any' ); ?>><?php esc_html_e( 'Any rule (OR)', 'narrative-forms' ); ?></option>
					<option value="all" <?php selected( $mode, 'all' ); ?>><?php esc_html_e( 'All rules (AND)', 'narrative-forms' ); ?></option>
				</select>
			</p>

			<table class="widefat striped" style="margin-top:10px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'If Field', 'narrative-forms' ); ?></th>
						<th><?php esc_html_e( 'Operator', 'narrative-forms' ); ?></th>
						<th><?php esc_html_e( 'Value', 'narrative-forms' ); ?></th>
						<th><?php esc_html_e( 'Then', 'narrative-forms' ); ?></th>
						<th><?php esc_html_e( 'Target Field', 'narrative-forms' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="nrfm-conditional-rows">
					<?php
					$index = 0;
					foreach ( $rules as $rule ) {
						echo $this->render_row( $index, $fields, $rule ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_row escapes internally.
						$index++;
					}
					?>
				</tbody>
			</table>

			<p style="margin-top:10px;">
				<button type="button" class="button" id="nrfm-add-condition" data-next-index="<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Add Rule', 'narrative-forms' ); ?></button>
			</p>

			<template id="nrfm-conditional-row-template">
				<?php echo $this->render_row( '__INDEX__', $fields, array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_row escapes internally. ?>
			</template>
		</div>
		<?php
	}

	private function render_row( $index, $fields, $rule ) {
		$field    = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		$op       = isset( $rule['operator'] ) ? (string) $rule['operator'] : 'equals';
		$value    = isset( $rule['value'] ) ? (string) $rule['value'] : '';
		$action   = isset( $rule['action'] ) ? (string) $rule['action'] : 'show';
		$target   = isset( $rule['target'] ) ? (string) $rule['target'] : '';

		$op_options = array(
			'equals'     => __( 'Equals', 'narrative-forms' ),
			'not_equals' => __( 'Not equals', 'narrative-forms' ),
			'contains'   => __( 'Contains', 'narrative-forms' ),
			'greater'    => __( 'Greater than', 'narrative-forms' ),
			'less'       => __( 'Less than', 'narrative-forms' ),
			'empty'      => __( 'Is empty', 'narrative-forms' ),
			'not_empty'  => __( 'Is not empty', 'narrative-forms' ),
		);
		$action_options = array(
			'show' => __( 'Show', 'narrative-forms' ),
			'hide' => __( 'Hide', 'narrative-forms' ),
		);

		ob_start();
		?>
		<tr>
			<td>
				<select name="nrfm_conditions[<?php echo esc_attr( $index ); ?>][field]">
					<?php foreach ( $fields as $name ) : ?>
						<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $field, $name ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="nrfm_conditions[<?php echo esc_attr( $index ); ?>][operator]">
					<?php foreach ( $op_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $op, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<input type="text" name="nrfm_conditions[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
			</td>
			<td>
				<select name="nrfm_conditions[<?php echo esc_attr( $index ); ?>][action]">
					<?php foreach ( $action_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $action, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="nrfm_conditions[<?php echo esc_attr( $index ); ?>][target]">
					<?php foreach ( $fields as $name ) : ?>
						<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $target, $name ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<button type="button" class="button nrfm-remove-condition" aria-label="<?php esc_attr_e( 'Remove rule', 'narrative-forms' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function save_rules( $form_id, $data ) {
		$mode = isset( $_POST['nrfm_conditions_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['nrfm_conditions_mode'] ) ) : 'any';
		$mode = in_array( $mode, array( 'all', 'any' ), true ) ? $mode : 'any';
		update_post_meta( $form_id, '_nrfm_conditions_mode', $mode );

		if ( empty( $_POST['nrfm_conditions'] ) || ! is_array( $_POST['nrfm_conditions'] ) ) {
			delete_post_meta( $form_id, '_nrfm_conditions' );
			return;
		}
		$form = new NRFM_Form( $form_id );
		$allowed_fields = $form->get_field_names();
		$allowed_map = array();
		foreach ( $allowed_fields as $name ) {
			$allowed_map[ $name ] = true;
		}

		$allowed_ops = array( 'equals', 'not_equals', 'contains', 'greater', 'less', 'empty', 'not_empty' );
		$allowed_actions = array( 'show', 'hide' );

		$raw_rules = wp_unslash( $_POST['nrfm_conditions'] );
		$rules = array();
		foreach ( $raw_rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$field  = isset( $rule['field'] ) ? sanitize_text_field( $rule['field'] ) : '';
			$target = isset( $rule['target'] ) ? sanitize_text_field( $rule['target'] ) : '';
			$op     = isset( $rule['operator'] ) ? sanitize_text_field( $rule['operator'] ) : 'equals';
			$action = isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : 'show';
			$value  = isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '';

			if ( empty( $field ) || empty( $target ) ) {
				continue;
			}
			if ( empty( $allowed_map[ $field ] ) || empty( $allowed_map[ $target ] ) ) {
				continue;
			}
			if ( ! in_array( $op, $allowed_ops, true ) ) {
				$op = 'equals';
			}
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				$action = 'show';
			}
			if ( in_array( $op, array( 'empty', 'not_empty' ), true ) ) {
				$value = '';
			}

			$rules[] = array(
				'field'    => $field,
				'operator' => $op,
				'value'    => $value,
				'action'   => $action,
				'target'   => $target,
			);
		}

		if ( empty( $rules ) ) {
			delete_post_meta( $form_id, '_nrfm_conditions' );
			return;
		}
		update_post_meta( $form_id, '_nrfm_conditions', $rules );
	}

	public function queue_rules_for_frontend( $html, $form ) {
		if ( is_admin() || ! ( $form instanceof NRFM_Form ) ) {
			return $html;
		}
		$form_id = $form->get_id();
		$rules = get_post_meta( $form_id, '_nrfm_conditions', true );
		$mode  = get_post_meta( $form_id, '_nrfm_conditions_mode', true );
		$mode  = in_array( $mode, array( 'all', 'any' ), true ) ? $mode : 'any';
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $html;
		}
		$this->rules_queue[ $form_id ] = array(
			'mode'  => $mode,
			'rules' => $rules,
		);
		return $html;
	}

	public function enqueue_frontend() {
		if ( empty( $this->rules_queue ) ) {
			return;
		}
		wp_register_script( 'nrfm-conditional', NRFM_PLUGIN_URL . 'assets/js/conditional-logic.js', array(), NRFM_VERSION, true );
		wp_enqueue_script( 'nrfm-conditional' );
		$payload = array();
		foreach ( $this->rules_queue as $form_id => $rules ) {
			$payload[ (string) $form_id ] = $rules;
		}
		wp_add_inline_script( 'nrfm-conditional', 'window.NRFM_ConditionalRules = ' . wp_json_encode( $payload ) . ';', 'before' );
	}

	public function enqueue_admin( $hook ) {
		if ( ! nrfm_is_form_edit_screen( $hook ) ) {
			return;
		}
		wp_enqueue_script( 'nrfm-conditional-admin', NRFM_PLUGIN_URL . 'assets/js/conditional-logic-admin.js', array( 'jquery' ), NRFM_VERSION, true );
	}

	/**
	 * Server-side guard: drop conditionally-hidden fields from core's
	 * required-field check so a hidden required field can't block submission.
	 * Mirrors the client engine in assets/js/conditional-logic.js.
	 *
	 * @param string[]  $required_fields Field names core parsed as required.
	 * @param array     $data            Cleaned submission data.
	 * @param NRFM_Form $form            The form being validated.
	 * @return string[] Required field names with hidden targets removed.
	 */
	public function filter_required_fields( $required_fields, $data, $form ) {
		if ( ! is_array( $required_fields ) || empty( $required_fields ) ) {
			return $required_fields;
		}
		if ( ! ( $form instanceof NRFM_Form ) ) {
			return $required_fields;
		}
		$hidden = $this->get_hidden_targets( $form->get_id(), is_array( $data ) ? $data : array() );
		if ( empty( $hidden ) ) {
			return $required_fields;
		}
		$hidden_map = array_flip( $hidden );
		return array_values(
			array_filter(
				$required_fields,
				function( $name ) use ( $hidden_map ) {
					return ! isset( $hidden_map[ $name ] );
				}
			)
		);
	}

	/**
	 * Evaluate this form's conditions against submitted data and return the list
	 * of target field names that end up hidden. PHP port of applyRules() in
	 * assets/js/conditional-logic.js — keep the two in sync.
	 */
	private function get_hidden_targets( $form_id, $data ) {
		$rules = get_post_meta( $form_id, '_nrfm_conditions', true );
		$mode  = get_post_meta( $form_id, '_nrfm_conditions_mode', true );
		$mode  = in_array( $mode, array( 'all', 'any' ), true ) ? $mode : 'any';
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return array();
		}

		// Defaults: 'show' targets start hidden; 'hide' targets start visible.
		$defaults = array();
		foreach ( $rules as $rule ) {
			if ( empty( $rule['target'] ) ) {
				continue;
			}
			$target = $rule['target'];
			$action = isset( $rule['action'] ) ? $rule['action'] : 'show';
			if ( $action === 'show' ) {
				$defaults[ $target ] = false;
			} elseif ( ! isset( $defaults[ $target ] ) ) {
				$defaults[ $target ] = true;
			}
		}

		// Group rules by target + action.
		$grouped = array();
		foreach ( $rules as $rule ) {
			if ( empty( $rule['target'] ) ) {
				continue;
			}
			$action = isset( $rule['action'] ) ? $rule['action'] : 'show';
			$key    = $rule['target'] . '|' . $action;
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array();
			}
			$grouped[ $key ][] = $rule;
		}

		// A matched group decides its target's visibility.
		$decisions = array();
		foreach ( $grouped as $group_rules ) {
			$action  = isset( $group_rules[0]['action'] ) ? $group_rules[0]['action'] : 'show';
			$target  = $group_rules[0]['target'];
			$matches = array();
			foreach ( $group_rules as $rule ) {
				$field     = isset( $rule['field'] ) ? $rule['field'] : '';
				$operator  = isset( $rule['operator'] ) ? $rule['operator'] : 'equals';
				$value     = isset( $rule['value'] ) ? $rule['value'] : '';
				$matches[] = $this->rule_matches( $this->get_submitted_values( $data, $field ), $operator, $value );
			}
			$ok = ( $mode === 'all' ) ? ! in_array( false, $matches, true ) : in_array( true, $matches, true );
			if ( $ok ) {
				$decisions[ $target ] = ( $action === 'show' );
			}
		}

		// Final visibility = decision if any, else default, else visible.
		$hidden  = array();
		$targets = array_unique( array_merge( array_keys( $defaults ), array_keys( $decisions ) ) );
		foreach ( $targets as $target ) {
			if ( isset( $decisions[ $target ] ) ) {
				$visible = $decisions[ $target ];
			} elseif ( isset( $defaults[ $target ] ) ) {
				$visible = $defaults[ $target ];
			} else {
				$visible = true;
			}
			if ( ! $visible ) {
				$hidden[] = $target;
			}
		}
		return $hidden;
	}

	/**
	 * Read submitted values for a field name as an array of strings.
	 */
	private function get_submitted_values( $data, $name ) {
		if ( $name === '' || ! isset( $data[ $name ] ) ) {
			return array();
		}
		$value = $data[ $name ];
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $v ) {
				if ( is_scalar( $v ) ) {
					$out[] = (string) $v;
				}
			}
			return $out;
		}
		return array( (string) $value );
	}

	/**
	 * PHP port of matchRule() in assets/js/conditional-logic.js.
	 */
	private function rule_matches( $values, $operator, $rule_value ) {
		$has_value = false;
		foreach ( $values as $v ) {
			if ( $v !== '' ) {
				$has_value = true;
				break;
			}
		}
		$rule_val = strtolower( (string) $rule_value );
		$vals     = array_map(
			function( $v ) {
				return strtolower( (string) $v );
			},
			$values
		);
		switch ( $operator ) {
			case 'empty':
				return ! $has_value;
			case 'not_empty':
				return $has_value;
			case 'contains':
				if ( $rule_val === '' ) {
					return false;
				}
				foreach ( $vals as $v ) {
					if ( strpos( $v, $rule_val ) !== false ) {
						return true;
					}
				}
				return false;
			case 'not_equals':
				foreach ( $vals as $v ) {
					if ( $v === $rule_val ) {
						return false;
					}
				}
				return true;
			case 'greater':
			case 'less':
				if ( ! is_numeric( $rule_value ) ) {
					return false;
				}
				$rule_num = (float) $rule_value;
				$nums     = array();
				foreach ( $values as $v ) {
					if ( is_numeric( $v ) ) {
						$nums[] = (float) $v;
					}
				}
				if ( empty( $nums ) ) {
					return false;
				}
				foreach ( $nums as $n ) {
					if ( $operator === 'greater' && $n > $rule_num ) {
						return true;
					}
					if ( $operator === 'less' && $n < $rule_num ) {
						return true;
					}
				}
				return false;
			case 'equals':
			default:
				foreach ( $vals as $v ) {
					if ( $v === $rule_val ) {
						return true;
					}
				}
				return false;
		}
	}
}
