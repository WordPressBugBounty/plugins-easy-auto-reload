<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Easy Auto Reload
 * Plugin URI:        https://infinitumform.com
 * Description:       Auto refresh WordPress pages if there is no site activity after after any number of minutes.
 * Version:           2.0.0
 * Author:            Ivijan-Stefan Stipic
 * Author URI:        https://www.linkedin.com/in/ivijanstefanstipic/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       autorefresh
 * Domain Path:       /languages
 * Network:           true
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
 
// If someone try to called this file directly via URL, abort.
if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_Auto_Refresh{
	/*
	 * Private cached class object
	 */
	private static $instance;
	private $options;
	
	/*
	 * Actions and filters
	 */
	private function __construct () {
		// Include textdomain and other plugin features
		add_action('plugins_loaded', [&$this, 'plugins_loaded'], 1, 0);
		// Add reload scripts to the site
		add_action('wp_head', [&$this, 'add_script'], 1, 0);
		if( $this->enable_in_admin() ) {
			add_action('admin_head', [&$this, 'add_script'], 1, 0);
		}
		// Admin functionalities
		add_action('admin_init', [&$this, 'admin_init'], 10, 0);
		add_action('admin_menu', [&$this, 'admin_menu'], 10, 0);
		add_action('add_meta_boxes', [&$this, 'add_meta_box'], 10, 0);
		add_action('save_post', [&$this, 'save_meta_box_data'], 10, 1);
		// Deactivation
		register_deactivation_hook(__FILE__, function(){
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			// Unload textdomain
			if ( is_textdomain_loaded( 'autorefresh' ) ) {
				unload_textdomain( 'autorefresh' );
			}
		});
		// Activation
		register_activation_hook(__FILE__, function(){
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			
			if( !get_option('wp-autorefresh', array()) ) {
				update_option('wp-autorefresh', array(
					'post_type' => ['post', 'page'],
					'nonce_life' => DAY_IN_SECONDS,
					'timeout' => 5
				));
			}
		});
		
		if( apply_filters('autorefresh_nonce_life_enable', true) && DAY_IN_SECONDS !== $this->get_nonce_life() ) {
			add_filter('nonce_life', [&$this, 'nonce_life'], 10, 1);
		}
	}

	/*
	 * Nonce Life
	 */
	public function nonce_life ( $default_nonce_life ) {
		return $this->get_nonce_life();
	}
	
	/*
	 * Load plugin options and text domain
	 */
	public function plugins_loaded() {
		// Load options only if not already set
		if (empty($this->options)) {
			$this->options = get_option('wp-autorefresh', array());
		}
		
		// Define the text domain
		$textdomain = 'autorefresh';
		
		// Check if the text domain is already loaded
		if (!is_textdomain_loaded($textdomain)) {
			// Determine the locale
			$locale = apply_filters("{$textdomain}_locale", get_locale(), $textdomain);
			
			// Standard .mo file format
			$mofile = "{$textdomain}-{$locale}.mo";

			// Order of loading .mo files
			// 1. Load from `/wp-content/languages/plugins`
			$loaded = load_textdomain($textdomain, WP_LANG_DIR . "/plugins/{$mofile}");

			// 2. Load from `/wp-content/languages`
			if (!$loaded) {
				$loaded = load_textdomain($textdomain, WP_LANG_DIR . "/{$mofile}");
			}

			// 3. Load from `/wp-content/plugins/autorefresh/languages`
			if (!$loaded) {
				$domain_path = __DIR__ . '/languages';
				$loaded = load_textdomain($textdomain, "{$domain_path}/{$mofile}");

				// 4. Load with just the locale, without the prefix
				if (!$loaded) {
					$loaded = load_textdomain($textdomain, "{$domain_path}/{$locale}.mo");
				}
			}

			// If all else fails, use load_plugin_textdomain
			if (!$loaded) {
				load_plugin_textdomain($textdomain, false, "{$domain_path}");
			}
		}
	}

	
	/*
	 * Initialize admin settings
	 */
	public function admin_init(){
		register_setting(
            'wp-autorefresh', // Option group
            'wp-autorefresh', // Option name
            [&$this, 'sanitize'] // Sanitize
        );

        add_settings_section(
            'wp-autorefresh', // ID
            esc_attr__('Auto-Refresh Settings','autorefresh'), // Title
            [&$this, 'print_section_info'], // Callback
            'wp-autorefresh' // Page
        );
		
		add_settings_field(
            'timeout', // ID
            esc_attr__('Auto-Refresh Timeout','autorefresh'), // Title 
            [&$this, 'input_timeout__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );
		
		add_settings_field(
            'clear_cache', // ID
            esc_attr__('Browser Cache','autorefresh'), // Title 
            [&$this, 'input_clear_cache__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );
		
		add_settings_field(
            'wp_admin', // ID
            esc_attr__('WP Admin','autorefresh'), // Title 
            [&$this, 'input_wp_admin__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );

		if( apply_filters('autorefresh_nonce_life_enable', true) ) {
			add_settings_field(
				'nonce_life', // ID
				esc_attr__('Lifespan of nonces','autorefresh'), // Title 
				[&$this, 'input_nonce_life__callback'], // Callback
				'wp-autorefresh', // Page
				'wp-autorefresh' // Section
			);
		}
		
		add_settings_field(
            'post_type', // ID
            esc_attr__('Allow custom refresh in page and post types','autorefresh'), // Title 
            [&$this, 'input_post_types__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );
	}
	
	/*
	 * Register admin menu pages
	 */
	public function admin_menu(){
		add_submenu_page(
			'options-general.php',
			esc_attr__('Auto-Refresh','autorefresh'),
			esc_attr__('Auto-Refresh','autorefresh'),
			'administrator',
			'wp-autorefresh',
			[&$this, 'options_page'],
			6
		);
	}
	
	 /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        
		if( isset( $input['timeout'] ) ) {
            $new_input['timeout'] = absint( $input['timeout'] );
		}
		
		if( isset( $input['clear_cache'] ) ) {
            $new_input['clear_cache'] = absint( $input['clear_cache'] );
		}
		
		if( isset( $input['wp_admin'] ) ) {
            $new_input['wp_admin'] = absint( $input['wp_admin'] );
		}

		if( isset( $input['post_type'] ) ) {
            $new_input['post_type'] = array_filter(
				is_array($input['post_type']) 
				? array_map('sanitize_text_field', $input['post_type']) 
				: []
			);
		}
		
		if( isset( $input['nonce_life'] ) ) {
            $new_input['nonce_life'] = absint( $input['nonce_life'] );
		}

        return $new_input;
    }
	
	/*
	 * Create options page
	 */
	public function options_page(){
		if(!empty($this->options)) {
			$this->options = get_option('wp-autorefresh', array());
		}
        ?>
        <div class="wrap">
            <h1><?php _e('Auto-Refresh','autorefresh'); ?></h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'wp-autorefresh' );
                do_settings_sections( 'wp-autorefresh' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
	}
	
	/*
	 * Section
	 */
	public function print_section_info(){
		printf('<p>%s</p>', __('Automatically reloads web pages after any number of minutes if the user or visitor is not active on the site.','autorefresh'));
	}
	
	/*
	 * Timeout settings field
	 */
	public function input_timeout__callback(){
		printf(
            '<input type="number" min="1" step="1" id="timeout" name="wp-autorefresh[timeout]" value="%d" /><p class="description">%s</p>',
            esc_attr($this->get_timeout()),
			__('Enter the number in minutes.','autorefresh')
        );
	}

	/*
	 * Timeout settings field
	 */
	public function input_nonce_life__callback(){
		printf(
            '<input type="number" min="1" step="1" id="nonce_life" name="wp-autorefresh[nonce_life]" value="%d" /><p class="description"><strong>%s</strong><br>%s</p>',
            esc_attr($this->get_nonce_life()),
			__('WARNING: Do not change if you are not sure what it is for.','autorefresh'),
			__('This field is used to define the lifespan of nonces in seconds. By default, nonces have a lifespan of 86,400 seconds, which is equivalent to one day. It\'s important to exercise caution when considering any extensions to this value, as longer lifespans may introduce security risks by extending the window of opportunity for potential attacks. Please ensure you carefully assess your security requirements before making changes to this setting.','autorefresh')
        );
	}
	
	/*
	 * Clear Browser cahce
	 */
	public function input_clear_cache__callback(){
		printf(
            '<label for="clear_cache"><input type="checkbox" id="clear_cache" name="wp-autorefresh[clear_cache]" value="1"%s/>%s</label>',
            ($this->clear_cache() ? ' checked' : ''),
			__('Clear the browser cache during refresh.','autorefresh')
        );
	}
	
	/*
	 * Enable autoefresh inside WP Admin
	 */
	public function input_wp_admin__callback(){
		printf(
            '<label for="wp_admin"><input type="checkbox" id="wp_admin" name="wp-autorefresh[wp_admin]" value="1"%s/>%s</label>',
            ($this->enable_in_admin() ? ' checked' : ''),
			__('Enable autoefresh inside WP Admin.','autorefresh')
        );
	}
	
	/*
	 * Enable autoefresh to Post Types
	 */
	public function input_post_types__callback(){
		$post_types = get_post_types( [
			'public'   => true,
			'show_in_menu' => true
		], 'objects');
		
		$i = 0;
		foreach ( $post_types as $post_type ) {
			if ( $post_type->name !== 'attachment' && post_type_supports( $post_type->name, 'editor' ) ) {
				printf(
					'<label for="post_type_%1$d" style="margin-right:15px;"><input type="checkbox" id="post_type_%1$d" name="wp-autorefresh[post_type][%1$d]" value="%2$s"%3$s/>%4$s</label>',
					$i,
					$post_type->name,
					(in_array($post_type->name, $this->enable_post_type()) ? ' checked' : ''),
					$post_type->label
				);
				++$i;
			}
		}
		
		printf(
			'<p style="margin-top:10px;">%s</p>',
			__('Enable autorefresh settings within pages and posts to have individual control.','autorefresh')
		);
	}
	
	/*
	 * Place JavaScript code inside `wp_head` to prevent brakeing by any other script.
	 * This must be placed inside document <head> area to working properly.
	 */
	public function add_script(){ ?>
	
<!-- <?php printf(__('Auto-reload WordPress pages after %d minutes if there is no site activity.','autorefresh'), esc_html($this->get_timeout())); ?> -->
<script>
/* <![CDATA[ */
(function() {
    if (typeof wp == 'undefined') {
        var wp = {};
    }

    wp.autorefresh = {
        setTimeOutId: null,
        events: {
            'DOMContentLoaded': 'document',
            'keyup': 'document',
            'click': 'document',
            'paste': 'document',
            'touchstart': 'window',
            'touchenter': 'window',
            'mousemove': 'window',
            'scroll': 'window',
            'scrollstart': 'window'
        },
        callback: function() {
            if (wp.autorefresh.setTimeOutId) {
                clearTimeout(wp.autorefresh.setTimeOutId);
            }
            wp.autorefresh.setTimeOutId = setTimeout(function() {
                <?php if($this->clear_cache()) : ?>
                var head = document.getElementsByTagName('head')[0],
                    script = document.createElement("script");

                script.src = "<?php echo rtrim(plugin_dir_url(__FILE__), '/') . '/assets/js/clear-browser-cache.js'; ?>";
                script.type = 'text/javascript';
                script.async = true;
                head.appendChild(script);
                script.onload = function() {
                    if (typeof caches !== 'undefined') {
                        caches.keys().then((keyList) => Promise.all(keyList.map((key) => caches.delete(key))));
                    } else {
                        // Fallback for browsers that do not support caches API
                        var cacheFallbackScript = document.createElement("script");
                        cacheFallbackScript.text = "if('serviceWorker' in navigator){navigator.serviceWorker.getRegistrations().then(function(registrations) {for(let registration of registrations) {registration.unregister();}});}";
                        head.appendChild(cacheFallbackScript);
                    }
                };
                <?php endif; ?>
                location.reload();
            }, (1e3 * 60 * <?php echo esc_attr($this->get_timeout()); ?>));
        }
    };

    for (var event in wp.autorefresh.events) {
        if (wp.autorefresh.events.hasOwnProperty(event)) {
            if (wp.autorefresh.events[event] === 'document') {
                document.addEventListener(event, wp.autorefresh.callback);
            } else if (wp.autorefresh.events[event] === 'window') {
                window.addEventListener(event, wp.autorefresh.callback);
            }
        }
    }
}());
/* ]]> */
</script>
<noscript><meta http-equiv="refresh" content="<?php echo esc_attr($this->get_timeout() * 60); ?>"></noscript>
	<?php }
	
	/*
	 * Add the metabox
	 */
	public function add_meta_box() {
		$enabled_post_types = $this->enable_post_type();

		if( !$enabled_post_types ) {
			return;
		}

		foreach ( $enabled_post_types as $post_type ) {
			add_meta_box(
				'easy-auto-reload',
				__('Auto Reload','autorefresh'),
				[&$this, 'meta_box__callback'],
				$post_type,
				'side',
				'high'
			);
		}
	}
	
	/*
	 * Metabox callback
	 */
	function meta_box__callback( $post ) {
		$select_value = get_post_meta( $post->ID, '_easy_auto_reload_mode', true )?:'automatic';
		$number_value = get_post_meta( $post->ID, '_easy_auto_reload_time', true )?:$this->get_timeout();
		?>
		<p>
			<label for="easy_auto_reload_mode"><?php esc_html_e('Refresh option:','autorefresh'); ?></label><br>
			<select name="_auto_reload_mode" id="easy_auto_reload_mode" style="width:100%; max-width:90%;">
				<option value="automatic" <?php selected( $select_value, 'automatic' ); ?>><?php esc_html_e('Automatic','autorefresh'); ?></option>
				<option value="custom" <?php selected( $select_value, 'custom' ); ?>><?php esc_html_e('Custom','autorefresh'); ?></option>
			</select>
		</p>
		<p>
			<label for="easy_auto_reload_time"><?php esc_html_e('Refresh interval in minutes:','autorefresh'); ?></label>
			<input type="number" name="_auto_reload_time" id="easy_auto_reload_time" value="<?php echo esc_attr( $number_value ); ?>" min="1" step="1" style="width:100%; max-width:100px;" />
		</p>
		<?php add_action('admin_footer', function() { ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var modeSelect = document.getElementById('easy_auto_reload_mode');
	var timeInput = document.getElementById('easy_auto_reload_time');

	function toggleTimeInput() {
		if (modeSelect.value === 'automatic') {
			timeInput.disabled = true;
		} else {
			timeInput.disabled = false;
		}
	}
	toggleTimeInput();
	modeSelect.addEventListener('change', toggleTimeInput);
});
</script>
		<?php }, 1, 0);
	}
	
	/*
	 * Save the metabox data
	 */
	public function save_meta_box_data( $post_id ) {
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['_auto_reload_mode'] ) ) {
			$select_value = sanitize_text_field( $_POST['_auto_reload_mode'] );
			if ( $select_value === 'custom' ) {
				update_post_meta( $post_id, '_easy_auto_reload_mode', $select_value );
			} else {
				delete_post_meta( $post_id, '_easy_auto_reload_mode' );
				delete_post_meta( $post_id, '_easy_auto_reload_time' );
			}
			unset($_POST['_auto_reload_mode']);
		}

		if ( isset( $_POST['_auto_reload_time'] ) ) {
			$number_value = absint( $_POST['_auto_reload_time'] );
			if ( $number_value >= 1 ) {
				update_post_meta( $post_id, '_easy_auto_reload_time', $number_value );
			} else {
				delete_post_meta( $post_id, '_easy_auto_reload_time' );
			}
			unset($_POST['_auto_reload_time']);
		}
	}
	
	
	/*
	 * Get timeout option on the safe way
	 */
	private function get_timeout(int $default = 5) {
		static $cached_timeout = null;

		if ($cached_timeout !== null) {
			return $cached_timeout;
		}

		// Vaša postojeća logika za izračunavanje $timeout vrednosti
		if ($post_id = $this->get_single_post_id()) {
			if (in_array(get_post_type($post_id), $this->enable_post_type())) {
				if (get_post_meta($post_id, '_easy_auto_reload_mode', true) === 'custom') {
					if ($timeout = absint(get_post_meta($post_id, '_easy_auto_reload_time', true) ?: $default)) {
						$cached_timeout = $timeout;
						return $timeout;
					}
				}
			}
		}

		$wp_autorefresh = (!empty($this->options) ? $this->options : get_option('wp-autorefresh', array()));

		if (isset($wp_autorefresh['timeout']) && ($timeout = absint($wp_autorefresh['timeout']))) {
			if (empty($timeout) || !is_numeric($timeout)) {
				$timeout = $default;
			}

			if ($timeout < 1) {
				$timeout = $default;
			}

			$cached_timeout = absint($timeout);
			return $cached_timeout;
		}

		$cached_timeout = $default;
		return $default;
	}


	/*
	 * Get timeout option on the safe way
	 */
	private function get_nonce_life(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );

		if(isset($wp_autorefresh['nonce_life']) && ($timeout=absint($wp_autorefresh['nonce_life']))){
			
			if( empty($timeout) || !is_numeric($timeout) ) {
				$timeout = apply_filters( 'nonce_life', DAY_IN_SECONDS );
			}
			
			if($timeout < 1) {
				$timeout = apply_filters( 'nonce_life', DAY_IN_SECONDS );
			}
			
			return absint($timeout);
		}
		
		return apply_filters( 'nonce_life', DAY_IN_SECONDS );
	}
	
	/*
	 * Is cache set for the clear
	 */
	private function clear_cache(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );
		return (isset( $wp_autorefresh['clear_cache'] ) && $wp_autorefresh['clear_cache'] ? true : false);
	}
	
	/*
	 * Enable autoefresh inside WP Admin
	 */
	private function enable_in_admin(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );
		return (isset( $wp_autorefresh['wp_admin'] ) && $wp_autorefresh['wp_admin'] ? true : false);
	}
	
	/*
	 * Enable autoefresh inside member types
	 */
	private function enable_post_type(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );
		return (isset( $wp_autorefresh['post_type'] ) && $wp_autorefresh['post_type'] ? $wp_autorefresh['post_type'] : []);
	}
	
	/*
	 * Get single post ID
	 */
	private function get_single_post_id() {
		if ( function_exists('get_the_ID') && function_exists('is_singular') && is_singular() ) {
			return get_the_ID();
		}
		return NULL;
	}
	
	/*
	 * Instance
	 */
	public static function instance(){
		if(!self::$instance){
			self::$instance = new self();
		}
		return self::$instance;
	}
}

/*
 * Run the plugin
 */
WP_Auto_Refresh::instance();