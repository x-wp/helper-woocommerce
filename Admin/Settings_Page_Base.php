<?php
/**
 * Extended_Settings_Page class file
 *
 * @package WooCommerce Sync Service
 * @subpackage WooCommerce
 */

namespace XWC\Admin;

/**
 * Extended settings page
 */
abstract class Settings_Page_Base extends \WC_Settings_Page {
    /**
     * Nested field names or IDs
     *
     * @var array<int, string>
     */
    protected array $nested_fields = array();
    /**
     * Array of extended settings
     *
     * @var array
     */
    protected array $settings;

    /**
     * Option name mask format
     *
     * @var string|null
     */
    protected ?string $opt_mask = null;

    /**
     * Class constructor
     *
     * @param string $id             Settings page ID.
     * @param string $label          Settings page label.
     */
    public function __construct( string $id, string $label ) {
        $this->id    = $id;
        $this->label = $label;

        parent::__construct();

        \add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'parse_settings' ), -1, 0 );
        \add_filter( 'woocommerce_get_settings_' . $this->id, array( $this, 'get_extended_settings' ), 50, 2 );
    }

    /**
     * Get the settings array
     *
     * @return array<string, array{
     *   enabled: bool,
     *   priority: int,
     *   section_name: string,
     *   fields: array<int, array<string, mixed>>,
     * }>
     */
    abstract protected function get_settings_array(): array;

    /**
     * Parse the settings
     *
     * @return array
     */
    public function parse_settings() {
        if ( isset( $this->settings ) ) {
            return $this->settings;
        }

        if ( ( $GLOBALS['current_tab'] ?? false ) !== $this->id ) {
            return array();
        }

        $settings = $this->get_settings_array();
        $settings = \apply_filters( 'xwc_get_raw_settings_' . $this->id, $settings );
        $settings = \wp_list_sort( $settings, 'priority', 'ASC', true );
        $settings = $this->run_field_callbacks( $settings );

        $this->settings = $settings;

        return $this->settings;
    }

    /**
     * Run the field callbacks
     *
     * @param  array $settings Settings array.
     * @return array
     */
    protected function run_field_callbacks( array $settings ): array {
        foreach ( $settings as $section => &$data ) {
            $method = "format_{$section}_fields";

            $data['fields'] = \method_exists( $this, $method )
                ? $this->$method( $data['fields'] )
                : $data['fields'];
        }

        return $settings;
    }

    /**
     * Get own sections
     *
     * @return array<string,string>
     */
    protected function get_own_sections() {
        return \wp_list_pluck(
            \wp_list_filter( $this->parse_settings(), array( 'enabled' => true ) ),
            'section_name',
        );
    }

    /**
     * Get the settings fields
     *
     * @param  array  $settings Settings array.
     * @param  string $section  Section ID.
     * @return array            Settings fields array.
     */
    public function get_extended_settings( array $settings, string $section ): array {
        if ( ! isset( $this->settings[ $section ] ) ) {
            return $settings;
        }

        $option = $this->format_key_base( $section );

        //phpcs:ignore Universal.Operators.DisallowShortTernary.Found
        $settings = $settings ?: $this->settings[ $section ]['fields'];
        $settings = \array_map( fn( $f ) => $this->format_field( $f, $option ), $settings );

        return $settings;
    }

    /**
     * Formats the base option key
     *
     * @param  string $section Section ID.
     * @return string
     */
    protected function format_key_base( string $section ): string {
        // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
        $section = $section ?: 'core';
        $mask    = $this->opt_mask ?? '%s_settings_%s';

        return \sprintf( $mask, $this->id, $section );
    }

    /**
     * Formats the field
     *
     * @param  array  $field  Field data.
     * @param  string $option Option name.
     * @return array
     */
    protected function format_field( array $field, string $option ): ?array {
        $field['field_name'] ??= $this->format_field_name( $field, $option );

        $field['id']      = (bool) ( $field['sub'] ?? false ) ? $field['sub'] . '_' . $field['id'] : $field['id'];
        $field['value'] ??= static fn() => \WC_Admin_Settings::get_option(
            $field['field_name'],
            $field['default'] ?? '',
        );

        return \array_map( static fn( $v ) => \is_callable( $v ) ? $v() : $v, $field );
    }

    /**
     * Formats the field name
     *
     * @param  array  $field  Field data.
     * @param  string $option Option name.
     * @return string
     */
    protected function format_field_name( array $field, string $option ): string {
        $option = (bool) ( $field['sub'] ?? false )
            ? $option . '--' . $field['sub']
            : $option;

        return $option . '[' . $field['id'] . ']';
    }

    /**
     * Santizes the double nested arrays, since WooCommerce doesn't support them
     *
     * @param  mixed $value     Sanitized value.
     * @param  array $option    Option array.
     * @param  mixed $raw Raw value.
     */
    final public function sanitize_nested_array( mixed $value, array $option, mixed $raw = array() ) {
        if ( ! $this->is_nested_option( $option, $raw ) ) {
            return $value;
        }

        $sanitize = $option['sanitize'] ?? 'wc_clean';

        return \array_filter(
            \array_map( $sanitize, \array_filter( \wc_string_to_array( $raw ) ) ),
        );
    }

    /**
     * Checks if the option is nested.
     *
     * @param  array $opt Option array.
     * @param  mixed $raw Raw value.
     * @return bool
     */
    protected function is_nested_option( array $opt, mixed $raw ): bool {
        $name = \rtrim( $opt['field_name'] ?? $opt['id'], '[]' );
        $id   = \rtrim( $opt['id'], '[]' );

        return \array_intersect( $this->nested_fields, array( $name, $id ) ) ||
            \str_ends_with( $opt['field_name'] ?? $opt['id'], '[]' ) ||
            \is_array( $raw );
    }
}
