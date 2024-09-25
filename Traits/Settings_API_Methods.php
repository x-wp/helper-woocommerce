<?php
/**
 * Settings_API_Methods trait file.
 *
 * @package eXtended WooCommerce
 * @subpackage Helpers
 */

namespace XWC\Traits;

trait Settings_API_Methods {
    /**
     * Array of settings
     *
     * @var array
     */
    protected array $settings;

    /**
     * Get the settings array from the database
     *
     * @param  string $option_key    The settings prefix.
     */
    public function load_options( string $option_key ) {
        if ( isset( $this->settings ) ) {
            return;
        }

        $option_key = \rtrim( $option_key, '_' );

        $settings = array();

        foreach ( $this->group_options( $option_key ) as $group => $subgroups ) {
            $this->parse_groups( $settings, $group, $subgroups );
		}

        $this->settings = $settings;
	}

    /**
     * Parse the groups
     *
     * @param  array  $settings  The settings array.
     * @param  string $group     The group to parse.
     * @param  array  $subgroups The subgroups to parse.
     */
    protected function parse_groups( &$settings, $group, $subgroups ) {
        if ( \is_array( $subgroups ) && ! \is_numeric( \key( $subgroups ) ) ) {
            $settings[ $group ] ??= array();
            foreach ( $subgroups as $subgroup => $options ) {
                $this->parse_groups( $settings[ $group ], $subgroup, $options );
            }
            return;
        }

        if ( \str_contains( $group, '_-_' ) ) {
            [ $first, $rest ] = $this->parse_subgroups( $settings, $group );

            $this->parse_groups( $settings[ $first ], $rest, $subgroups );
            return;
        }

        $settings[ $group ] = $this->parse_options( $subgroups );
    }

    /**
     * Parse the subgroups
     *
     * @param  array  $settings The settings array.
     * @param  string $group    The group to parse.
     * @return array
     */
    protected function parse_subgroups( &$settings, string $group ): array {
        $expl  = \explode( '_-_', $group );
        $first = \array_shift( $expl );
        $rest  = \implode( '_-_', $expl );

        $settings[ $first ] ??= array();

        return array( $first, $rest );
    }

    /**
     * Parse the option value
     *
     * @param  mixed $opts The options to parse.
     * @return mixed       The parsed options.
     */
    protected function parse_options( $opts ) {
        if ( ! \is_scalar( $opts ) && ! \is_null( $opts ) ) {
            return $opts;
        }

        $opts ??= '';

        $opts = \is_string( $opts ) ? \strtolower( \trim( $opts ) ) : $opts;

        return match ( $opts ) {
            1, '1', 'yes', 'true', 'on' => true,
            0, '0', 'no', 'false', 'off' => false,
            default => $opts,
        };
    }

    /**
     * Get the settings section from the database
     *
     * This function was added because of the dynamic sections
     *
     * @param  string $option_key       The option key base.
     * @return array<string,mixed>        The registered sections.
     */
	protected function group_options( string $option_key ) {
		$groups  = $this->get_options_from_db( $option_key );
		$groups  = \wp_list_pluck( $groups, 'option_value', 'option_name' );
		$groups  = \array_map( 'maybe_unserialize', $groups );
		$grouped = array();

		foreach ( $groups as $group => $options ) {
            if ( '' === $options ) {
                $options = array();
            }

            $exploded = \explode( '--', \str_replace( '_settings', '', $group ) );
            $this->subgroup_options( $grouped, $exploded, $options );
		}

		return $grouped;
	}

    /**
     * Split the options into subgroups
     *
     * @param  array $grouped  The grouped options.
     * @param  array $exploded The exploded group name.
     * @param  array $options  The options to group.
     */
    protected function subgroup_options( &$grouped, $exploded, $options ) {
        foreach ( $exploded as $sub ) {
            $grouped[ $sub ] ??= array();
            $grouped           = &$grouped[ $sub ];
        }

        if ( ! \is_array( $options ) ) {
            return;
        }

        foreach ( $options as $key => $values ) {
            $grouped[ $key ] = $values;
        }
    }

    /**
     * Get the options from the database
     *
     * @param  string $option_key The option key base.
     * @return array<string,mixed>  The options.
     */
	private function get_options_from_db( string $option_key ): array {
		global $wpdb;

		return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT REPLACE(option_name, %s, %s) as option_name, option_value FROM %i WHERE option_name LIKE %s',
                $option_key . '_',
                '',
                $wpdb->options,
                $wpdb->esc_like( $option_key ) . '%',
            ),
            \ARRAY_A,
		);
	}

    /**
     * Field filter callback to get only the ones that are needed.
     *
     * @param  array $field The field to filter.
     * @return bool
     */
    protected function filter_field( array $field ): bool {
        $ignored_fields = array( 'title', 'sectionend', 'info' );
        return ! \in_array( $field['type'], $ignored_fields, true ) && ! isset( $field['field_name'] );
    }

    /**
     * Get the settings array
     *
     * @param  string $section The section to get.
     * @param  string ...$args The sub-sections to get.
     * @return array<string, mixed>|mixed           Array of settings or a single setting.
     */
	public function get_settings( string $section = 'all', string ...$args ) {
		if ( 'all' === $section ) {
			return $this->settings;
		}

		$sub_section = $this->settings[ $section ] ?? array();

		foreach ( $args as $arg ) {
			$sub_section = $sub_section[ $arg ] ?? array();
		}

		return $sub_section;
	}
}
