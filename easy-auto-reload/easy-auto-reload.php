<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Easy Auto Reload
 * Plugin URI:        https://infinitumform.com
 * Description:       Auto refresh WordPress pages if there is no site activity after any number of minutes.
 * Version:           2.0.6
 * Author:            Ivijan-Stefan Stipic
 * Author URI:        https://www.linkedin.com/in/ivijanstefanstipic/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       autorefresh
 * Domain Path:       /languages
 * Network:           false
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
 *
 * Tested on:
 * - WordPress
 * - caffeine
 * - poor life decisions
 *
 * Not tested on Internet Explorer 8.
 * And it never will be.
 *
 * (But somehow... it probably still works)
 */
 
// If someone try to called this file directly via URL, abort.
if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Constant for no particular reason - maybe JS version.
 * Do not remove or change.
 * We are all afraid to find out what breaks.
 */
if ( ! defined( 'WP_AUTO_REFRESH_VERSION' ) ) { define( 'WP_AUTO_REFRESH_VERSION', '2.0.5' ); }

/*
 * Dear future developer:
 *
 * Before editing this final class,
 * ask yourself one important question:
 *
 * "Do I really need to?"
 *
 * It's just run the plugin.
 */
final class WP_Auto_Refresh{

	/*
	 * Private cached class object.
	 * Nobody knows why making it static fixed the bug,
	 * but nobody is brave enough to remove it.
	 */
	private static $instance;

	/*
	 * Plugin options.
	 * Also known as "future unexpected behavior configuration".
	 */
	private $options;

	/*
	 * Calculate the undeniable truth of the universe.
	 * After extensive research and several energy drinks,
	 * we concluded that true is, in fact, true.
	 */
	private const ITS_TRUE = 3/3;

	/*
	 * Calculate the black hole.
	 * Scientists fear this number.
	 * PHP somehow accepts it.
	 */
	private const ITS_FALSE = 3.1415 - 3.1415;
	
	/*
	 * Actions and filters.
	 *
	 * This is where all the weird shit starts happening.
	 *
	 * At first it was just:
	 * "add_action() here, add_filter() there..."
	 *
	 * Then suddenly:
	 * - sessions expire
	 * - browsers reload themselves
	 * - wp-admin gains consciousness
	 * - Gary deploys on Friday again
	 *
	 * If production breaks,
	 * statistically the problem started somewhere below this line.
	 */
	private function __construct () {
		// Include textdomain and other plugin features
		add_action('plugins_loaded', [$this, 'plugins_loaded'], 1, 0);
		// Add reload scripts to the site
		add_action('wp_head', [$this, 'add_script'], 1, 0);
		if( $this->enable_in_admin() ) {
			add_action('admin_head', [$this, 'add_script'], 1, 0);
		}
		// Admin functionalities
		add_action('admin_init', [$this, 'admin_init'], 10, 0);
		add_action('admin_menu', [$this, 'admin_menu'], 10, 0);
		add_action('add_meta_boxes', [$this, 'add_meta_box'], 10, 0);
		add_action('save_post', [$this, 'save_meta_box_data'], 10, 1);
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
					'timeout' => 5,
					'global_refresh' => 1
				));
			}
		});
		// Update plugin
		add_action('admin_init', function () {
			$current_version = get_option('wp-autorefresh-version');
			if ($current_version === false) {
			
				if( $option = get_option('wp-autorefresh', array()) ) {
					$option['global_refresh'] = 1;
					update_option('wp-autorefresh', $option);
				}
			
				update_option('wp-autorefresh-version', WP_AUTO_REFRESH_VERSION);
			}
		});
		
		// Add nonce life update
		if( apply_filters('autorefresh_nonce_life_enable', true) && DAY_IN_SECONDS !== $this->get_nonce_life() ) {
			add_filter('nonce_life', [$this, 'nonce_life'], 10, 1);
		}
	}
	
	
	/**************************************
	 * OK, I'm becoming serious here.
	 * Because this is where shit happens.
	 *
	 * But I make no guarantees until the end of this file.
	 **************************************/
	 
	
	/*
	 * Nonce Life
	 */
	public function nonce_life( $default_nonce_life ) {
		// Always return a raw value; never call apply_filters('nonce_life', ...) from here
		$life = $this->get_nonce_life();
		return ( is_int( $life ) && $life > (int)self::ITS_FALSE ) ? $life : $default_nonce_life;
	}
	
	/*
	 * Load plugin options and text domain
	 */
	public function plugins_loaded() {
		// Load options only if not already set
		if ( empty( $this->options ) ) {
			$this->options = get_option('wp-autorefresh', []);
		}

		$domain = 'autorefresh';

		// Preferred: relative path to /languages inside this plugin
		load_plugin_textdomain(
			$domain,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		// Manual fallback (optional)
		if ( ! is_textdomain_loaded( $domain ) ) {
			$locale = apply_filters( "{$domain}_locale", get_locale(), $domain );
			$domain_path = __DIR__ . '/languages';
			$mo_files = [
				"{$domain_path}/{$domain}-{$locale}.mo",
				"{$domain_path}/{$locale}.mo"
			];
			foreach ( $mo_files as $mo_file ) {
				if ( file_exists( $mo_file ) && load_textdomain( $domain, $mo_file ) ) {
					break;
				}
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
            [$this, 'sanitize'] // Sanitize
        );

        add_settings_section(
            'wp-autorefresh', // ID
            esc_attr__('Auto-Refresh Settings','autorefresh'), // Title
            [$this, 'print_section_info'], // Callback
            'wp-autorefresh' // Page
        );
		
		add_settings_field(
            'global_refresh', // ID
            esc_attr__('Auto-Refresh','autorefresh'), // Title 
            [$this, 'input_global_refresh__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );
		
		add_settings_field(
            'timeout', // ID
            esc_attr__('Auto-Refresh Timeout','autorefresh'), // Title 
            [$this, 'input_timeout__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );
		
		add_settings_field(
            'clear_cache', // ID
            esc_attr__('Browser Cache','autorefresh'), // Title 
            [$this, 'input_clear_cache__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );
		
		add_settings_field(
            'wp_admin', // ID
            esc_attr__('WP Admin','autorefresh'), // Title 
            [$this, 'input_wp_admin__callback'], // Callback
            'wp-autorefresh', // Page
            'wp-autorefresh' // Section
        );

		if( apply_filters('autorefresh_nonce_life_enable', true) ) {
			add_settings_field(
				'nonce_life', // ID
				esc_attr__('Lifespan of nonces','autorefresh'), // Title 
				[$this, 'input_nonce_life__callback'], // Callback
				'wp-autorefresh', // Page
				'wp-autorefresh' // Section
			);
		}
		
		add_settings_field(
            'post_type', // ID
            esc_attr__('Allow custom refresh in page and post types','autorefresh'), // Title 
            [$this, 'input_post_types__callback'], // Callback
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
			'manage_options', // <-- use capability, not role
			'wp-autorefresh',
			[$this, 'options_page'],
			6
		);
	}
	
	/**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input) {
		$fields = [
			'timeout',
			'clear_cache',
			'wp_admin',
			'global_refresh',
			'nonce_life'
		];

		$new_input = [];

		// Sanitize integer fields
		foreach ($fields as $field) {
			if (isset($input[$field])) {
				$new_input[$field] = absint($input[$field]);
			}
		}

		// Sanitize post_type separately since it's an array
		if (!empty($input['post_type']) && is_array($input['post_type'])) {
			$new_input['post_type'] = array_map('sanitize_text_field', array_filter($input['post_type']));
		}

		return $new_input;
	}

	
	/*
	 * Create options page
	 */
	public function options_page(){
		if(empty($this->options)) {
			$this->options = get_option('wp-autorefresh', array());
		}
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Auto-Refresh','autorefresh'); ?></h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'wp-autorefresh' );
                do_settings_sections( 'wp-autorefresh' );
                submit_button();
            ?>
            </form>
        </div>
        <?php add_action('admin_footer', function(){ ?>
<script src="https://storage.ko-fi.com/cdn/scripts/overlay-widget.js"></script>

<script>
kofiWidgetOverlay.draw('ivijanstefanstipic', {
	'type': 'floating-chat',
	'floating-chat.donateButton.text': '<?php esc_attr_e('Support Me','autorefresh'); ?>',
	'floating-chat.donateButton.background-color': '#f45d22',
	'floating-chat.donateButton.text-color': '#ffffff'
});

(function () {
	function moveKofiRight() {
		document.querySelectorAll(
			'[id^="kofi-widget-overlay-"] .floatingchat-container-wrap,' +
			'[id^="kofi-widget-overlay-"] .floatingchat-container-wrap-mobi'
		).forEach(function (element) {
			element.style.setProperty('left', 'auto', 'important');
			element.style.setProperty('right', '20px', 'important');
			element.style.setProperty('bottom', '20px', 'important');
			element.style.setProperty('position', 'fixed', 'important');
		});
		
		document.querySelectorAll(
			'[id^="kofi-widget-overlay-"] .floating-chat-kofi-popup-iframe,' +
			'[id^="kofi-widget-overlay-"] .floating-chat-kofi-popup-iframe-mobi'
		).forEach(function (element) {
			element.style.setProperty('left', 'auto', 'important');
			element.style.setProperty('right', '20px', 'important');
			element.style.setProperty('bottom', '72px', 'important');
			element.style.setProperty('position', 'fixed', 'important');
		});
	}

	moveKofiRight();

	new MutationObserver(moveKofiRight).observe(document.body, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: ['style', 'class']
	});
})();
</script>
		<?php });
	}
	
	/*
	 * Section
	 */
	public function print_section_info(){
		printf('<p>%s</p>', esc_html__('Automatically reloads web pages after any number of minutes if the user or visitor is not active on the site.','autorefresh'));
	}
	
	public function input_global_refresh__callback(){
		printf(
            '<label for="global_refresh"><input type="checkbox" id="global_refresh" name="wp-autorefresh[global_refresh]" value="1"%s/>%s</label><p class="description" style="margin-top:10px;"><strong>%s</strong> %s</p>',
            ($this->enable_global_refresh() ? ' checked' : ''),
			esc_html__('Enable auto refresh globally on the entire site.','autorefresh'),
			esc_html__('INFO:','autorefresh'),
			esc_html__('Even if this function is disabled, you can still choose for each page individually whether you want it to refresh.','autorefresh')
        );
	}
	
	/*
	 * Timeout settings field
	 */
	public function input_timeout__callback(){
		printf(
            '<input type="number" min="1" step="1" id="timeout" name="wp-autorefresh[timeout]" value="%d" /><p class="description">%s</p>',
            esc_attr($this->get_timeout()),
			esc_html__('Enter the number in minutes.','autorefresh')
        );
	}

	/*
	 * Timeout settings field
	 */
	public function input_nonce_life__callback(){
		printf(
            '<input type="number" min="1" step="1" id="nonce_life" name="wp-autorefresh[nonce_life]" value="%d" /><p class="description"><strong style="color: #cc0000;">%s</strong><br>%s</p>',
            esc_attr($this->get_nonce_life()),
			esc_html__('WARNING: Do not change if you are not sure what it is for.','autorefresh'),
			esc_html__('This field is used to define the lifespan of nonces in seconds. By default, nonces have a lifespan of 86,400 seconds, which is equivalent to one day. It\'s important to exercise caution when considering any extensions to this value, as longer lifespans may introduce security risks by extending the window of opportunity for potential attacks. Please ensure you carefully assess your security requirements before making changes to this setting.','autorefresh')
        );
	}
	
	/*
	 * Clear Browser cache
	 */
	public function input_clear_cache__callback(){
		printf(
            '<label for="clear_cache"><input type="checkbox" id="clear_cache" name="wp-autorefresh[clear_cache]" value="1"%s/>%s</label>',
            ($this->clear_cache() ? ' checked' : ''),
			esc_html__('Clear the browser cache during refresh.','autorefresh')
        );
	}
	
	/*
	 * Enable autorefresh inside WP Admin
	 */
	public function input_wp_admin__callback(){
		printf(
            '<label for="wp_admin"><input type="checkbox" id="wp_admin" name="wp-autorefresh[wp_admin]" value="1"%s/>%s</label>',
            ($this->enable_in_admin() ? ' checked' : ''),
			esc_html__('Enable autorefresh inside WP Admin.','autorefresh')
        );
	}
	
	/*
	 * Enable autorefresh to Post Types
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
					(in_array($post_type->name, $this->enable_post_type(), true) ? ' checked' : ''),
					$post_type->label
				);
				++$i;
			}
		}
		
		printf(
			'<p style="margin-top:10px;" class="description">%s</p>',
			esc_html__('Enable autorefresh settings within pages and posts to have individual control.','autorefresh')
		);
	}
	
	/*
	 * Place JavaScript code inside `wp_head` to prevent breaking by any other script.
	 * This must be placed inside document <head> area to working properly.
	 */
	public function add_script(){
		$can_disable = true;
		if ($post_id = $this->get_single_post_id()) {
			if (in_array(get_post_type($post_id), $this->enable_post_type(), true)) {
				if (get_post_meta($post_id, '_easy_auto_reload_mode', true) === 'disabled') {
					return;
				} else if (get_post_meta($post_id, '_easy_auto_reload_mode', true) === 'custom') {
					$can_disable = false;
				}
			}
		}
		
		
		if(!$this->enable_global_refresh() && $can_disable) {
			return;
		}
	?>
	
<!-- <?php printf(esc_html__('Auto-reload WordPress pages after %d minutes if there is no site activity.','autorefresh'), esc_html($this->get_timeout())); ?> --><?php ob_start(); ?>
<script>/* <![CDATA[ */
(function () {
    window.wp = window.wp || {};

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
        callback: function () {
            if (wp.autorefresh.setTimeOutId) {
                clearTimeout(wp.autorefresh.setTimeOutId);
            }
            wp.autorefresh.setTimeOutId = setTimeout(function () {
                <?php if ($this->clear_cache()) : ?>
                var head = document.head || document.getElementsByTagName('head')[0];
                if (!head) return;

                var script = document.createElement("script");
                script.src = "<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/js/clear-browser-cache.min.js'); ?>";
                script.type = 'text/javascript';
                script.async = true;
                head.appendChild(script);

                script.onload = function () {
                    if (typeof caches !== 'undefined' && caches.keys) {
                        caches.keys().then(function (keyList) {
                            return Promise.all(keyList.map(function (key) {
                                return caches.delete(key);
                            }));
                        }).catch(function (err) {
                            console.warn("<?php esc_attr_e('Cache clearing failed:','autorefresh'); ?>", err);
                        });
                    } else if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.getRegistrations().then(function (registrations) {
                            for (let registration of registrations) {
                                registration.unregister();
                            }
                        }).catch(function (err) {
                            console.warn("<?php esc_attr_e('Service Worker unregister failed:','autorefresh'); ?>", err);
                        });
                    }
                };
                <?php endif; ?>
				
                location.reload();
            }, 1000 * 60 * <?php echo json_encode($this->get_timeout()); ?>);
        }
    };

    Object.keys(wp.autorefresh.events).forEach(function (event) {
        var target = wp.autorefresh.events[event] === 'document' ? document : window;
        target.addEventListener(event, wp.autorefresh.callback);
    });
})();
/* ]]> */</script>
<noscript><meta http-equiv="refresh" content="<?php echo esc_attr($this->get_timeout() * 60); ?>"></noscript>
	<?php $js_code = ob_get_clean();
		echo preg_replace(
			[ '/\s+/', '/\s*([{};,:])\s*/' ],
			[ ' ', '$1' ],
			$js_code
		);
	}
	
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
				[$this, 'meta_box__callback'],
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
			<label for="easy_auto_reload_mode"><?php esc_html_e('Refresh option:', 'autorefresh'); ?></label><br>
			<select name="_auto_reload_mode" id="easy_auto_reload_mode" style="width:100%; max-width:90%;">
				<?php
				$options = [
					'automatic' => __('Automatic', 'autorefresh'),
					'custom'    => __('Custom', 'autorefresh'),
					'disabled'  => __('Disabled', 'autorefresh')
				];

				// Loop kroz opcije
				foreach ($options as $value => $label) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr($value),
						selected($select_value, $value, false),
						esc_html($label)
					);
				}
				?>
			</select>
		</p>
		<p>
			<label for="easy_auto_reload_time"><?php esc_html_e('Refresh interval in minutes:', 'autorefresh'); ?></label>
			<input 
				type="number" 
				name="_auto_reload_time" 
				id="easy_auto_reload_time" 
				value="<?php echo esc_attr($number_value); ?>" 
				min="1" 
				step="1" 
				style="width: 100%; max-width: 100px;" 
				<?php echo esc_html( in_array($select_value, ['disabled', 'automatic'], true) ? 'class="disabled" disabled' : '' ); ?>
			/>
		</p>
		<?php add_action('admin_footer', function() { ?>
<script>
/* <![CDATA[ */
document.addEventListener('DOMContentLoaded', function () {
	var modeSelect = document.getElementById('easy_auto_reload_mode');
	var timeInput = document.getElementById('easy_auto_reload_time');

	function toggleTimeInput() {
		if (['automatic', 'disabled'].indexOf(modeSelect.value) !== -1) {
			timeInput.disabled = true;
			timeInput.classList.add('disabled');
		} else {
			timeInput.disabled = false;
			timeInput.classList.remove('disabled');
		}
	}
	toggleTimeInput();
	modeSelect.addEventListener('change', toggleTimeInput);
});
/* ]]> */
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
			if ( in_array($select_value, ['custom', 'disabled'], true) ) {
				update_post_meta( $post_id, '_easy_auto_reload_mode', $select_value );
			} else {
				delete_post_meta( $post_id, '_easy_auto_reload_mode' );
				delete_post_meta( $post_id, '_easy_auto_reload_time' );
			}
			unset($_POST['_auto_reload_mode']);
		}

		if ( isset( $_POST['_auto_reload_time'] ) ) {
			$number_value = absint( $_POST['_auto_reload_time'] );
			if ( $number_value >= self::ITS_TRUE ) {
				update_post_meta( $post_id, '_easy_auto_reload_time', $number_value );
			} else {
				delete_post_meta( $post_id, '_easy_auto_reload_time' );
			}
			unset($_POST['_auto_reload_time']);
		}
	}
	
	
	/**
	 * Returns the effective timeout (in minutes) for the current context, with static caching.
	 * Order of precedence:
	 * 1) Per-post custom setting (if enabled for the post type and mode is 'custom')
	 * 2) Global plugin setting
	 * 3) Provided $default
	 */
	private function get_timeout(int $default = 5) {

		static $cached_timeout = null;

		if ($cached_timeout !== null) {
			return $cached_timeout;
		}

		// Vaša postojeća logika za izračunavanje $timeout vrednosti
		if ($post_id = $this->get_single_post_id()) {
			if (in_array(get_post_type($post_id), $this->enable_post_type(), true)) {
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

			if ($timeout < self::ITS_TRUE) {
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
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', []) );

		if ( isset( $wp_autorefresh['nonce_life'] ) ) {
			$timeout = (int) $wp_autorefresh['nonce_life'];
			// If invalid, fall back to core default constant (no filters!)
			if ( $timeout < 1 ) {
				return DAY_IN_SECONDS;
			}
			return $timeout;
		}

		// Default (no filter call here to avoid recursion)
		return DAY_IN_SECONDS;
	}
	
	/*
	 * Is cache set for the clear
	 */
	private function clear_cache(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );
		return ($wp_autorefresh['clear_cache'] ?? false ? true : false);
	}
	
	/*
	 * Enable autorefresh inside WP Admin
	 */
	private function enable_in_admin(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );
		return ($wp_autorefresh['wp_admin'] ?? false ? true : false);
	}
	
	/*
	 * Enable autorefresh inside member types
	 */
	private function enable_post_type(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', array()) );
		return ( $wp_autorefresh['post_type'] ?? [] );
	}
	
	/*
	 * Enable autorefresh inside member types
	 */
	private function enable_global_refresh(){
		$wp_autorefresh = ( !empty($this->options) ? $this->options : get_option('wp-autorefresh', []) );
		return (int)( $wp_autorefresh['global_refresh'] ?? self::ITS_FALSE ) === (int)self::ITS_TRUE;
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
 * Run the plugin.
 *
 * This is the exact moment responsibility leaves our hands.
 */
WP_Auto_Refresh::instance();

/*
╔═════════════════════════════════════════════════════════════════════════╗
║                                                                         ║
║   ███████╗ █████╗ ███████╗██╗   ██╗                                     ║
║   ██╔════╝██╔══██╗██╔════╝╚██╗ ██╔╝                                     ║
║   █████╗  ███████║███████╗ ╚████╔╝                                      ║
║   ██╔══╝  ██╔══██║╚════██║  ╚██╔╝                                       ║
║   ███████╗██║  ██║███████║   ██║                                        ║
║   ╚══════╝╚═╝  ╚═╝╚══════╝   ╚═╝                                        ║
║                                                                         ║
║        A U T O   R E L O A D   -   T H E   L E G E N D                  ║
║                                                                         ║
╚═════════════════════════════════════════════════════════════════════════╝

Once upon a production server...

There was a developer.
Not a senior developer.
Not a junior developer.
Just... a developer.

It was late.

Coffee count: 6
Open Chrome tabs: 47
Emotional stability: undefined

The mission was simple:

"Add automatic page refresh."

That was it.
A tiny feature.
A harmless feature.
A feature so innocent it sounded like something a toaster could implement.

And yet...

something ancient awakened.

The first version worked perfectly.
Which was suspicious.

The second version worked even better.
Which was terrifying.

Then someone said:

    "Can we make it work in WP Admin too?"

And that...
that was the moment reality split into multiple timelines.

Somewhere in another universe, the developer became a farmer.
A peaceful man.
Growing tomatoes.
Sleeping 8 hours.

But not here.

Here we added:

    - nonce lifespan logic
    - custom post type controls
    - dynamic reload intervals
    - cache clearing
    - admin integration
    - JavaScript events from the depths of hell itself

At one point the browser refreshed itself so aggressively
that QA reported:

    "The site feels alive."

We don't talk about version 1.7 anymore.

There was also an intern once.

He asked:

    "Why don't we just use location.reload()?"

Nobody ever saw him again.

Some say he still wanders GitHub Discussions,
asking dangerous questions.

Legend says if you stare into this file long enough,
you can hear distant whispers:

    "Just one more optimization..."
    "It works on my machine..."
    "Clear cache and try again..."

And then...

the final boss appeared.

A developer named Gary.

Gary deployed directly to production.
On Friday.
Without testing.

Gary said things like:

    "How bad could it be?"
    "Users probably won't notice."
    "Let's hotfix live."

Gary is the reason this plugin has comments like these.

So if you are reading this now...
in the darkness of wp-admin...
with DevTools open...
and one trembling hand on Ctrl+S...

remember:

You are not alone.

Thousands before you have entered this file.
Many never returned.

Some became senior developers.
Some became project managers.
One opened a bakery.

But all of them learned the same lesson:

    Never trust a "small WordPress change".

Especially when JavaScript says:

        setTimeout(...)

because that's where the adventure begins.

                .-=========-.
                \'-=======-'/  
                _|   .=.   |_  
               ((|  {{1}}  |)) 
                \|   /|\   |/  
                 \__ '`' __/   
                   _`) (`_     
                 _/_______\_   
                /___________\

            "Deploying to production..."

If everything still works after your edits:

Congratulations.

You are now the senior developer.

Which means the next disaster is officially your problem.

Good luck.

╔══════════════════════════════════════════════════════════════╗
  THE SEQUEL NOBODY ASKED FOR
╚══════════════════════════════════════════════════════════════╝

Time passed.

The plugin survived.

Somehow.

Users were happy.
Servers were stable.
CPU usage stopped looking like cryptocurrency mining.

Peace had finally returned.

Until...

a ticket appeared.

Subject:

   "Auto Refresh not working on Internet Explorer 8"

Silence filled the room.

One developer slowly stood up,
removed his glasses,
and whispered:

   "No..."


Emergency meeting started immediately.

Someone suggested:

   "Maybe we should support it?"

Security escorted him out of the building.


Meanwhile...

deep inside the codebase...

the JavaScript watched.

Waiting.


Then came THE CLIENT.

Every developer has heard the prophecy.

The mythical sentence.


   "It was working before."


Nobody knew what "before" meant.

Before WHAT?

Before cache?
Before update?
Before the server caught fire?
Before Gary touched production?

History had become unclear.


The logs revealed nothing useful:

   [INFO] something happened
   [WARNING] something worse happened
   [ERROR] ask Gary


Days turned into nights.
Nights turned into energy drinks.
Energy drinks turned into anxiety.


One brave soul finally opened the file again.

His IDE immediately froze.

Coincidence?

Probably.

But nobody wanted to risk it.


He kept scrolling...

past functions...
past hooks...
past comments written by developers who no longer worked there...

until he discovered...

               T H E    C O D E


Nobody remembered writing it.


It looked like this:

     if (
         $reload === true &&
         $user_is_idle &&
         !$server_is_crying &&
         !$friday_deployment
     ) {
         location.reload();
     }


Beautiful.
Terrifying.
Illegal in at least 3 countries.


Then suddenly...

the office lights flickered.

A monitor turned on by itself.

Build pipeline failed.

Slack notification appeared:

   "Production updated successfully."


Nobody deployed anything.


Slowly...
very slowly...

everyone turned toward Gary.


Gary was not there.


Only his empty chair remained.

Spinning gently.


On the desk:

a sticky note.


It said:

   "Fixed small typo in refresh logic."


Attached below:

   git push --force


Entire infrastructure entered survival mode.

Browsers refreshed every 0.4 seconds.
Admin dashboard opened in ancient Sanskrit.
One printer in accounting started printing div elements.


Somewhere...

deep in the server room...

a single fan screamed.


The senior developer took one final sip of cold coffee,
opened terminal,
and typed:

   wp plugin deactivate easy-auto-reload


The room became silent.

Peace returned.

Birds started singing.
CPU temperature dropped 20 degrees.
Someone saw sunlight for the first time in weeks.


Then...

a new ticket arrived.


Subject:

   "Why did auto refresh stop working?"


And thus...

the cycle began again.


                           __________
                        .-"          "-.
                      .'   DON'T PUSH   '.
                     /    TO PRODUCTION   \
                    ;       ON FRIDAY      ;
                    |                      |
                    ;      ESPECIALLY      ;
                     \        GARY.       /
                      '.                .'
                        '-.__________.-'


If you reached this point in the file,
you are either:

    [ ] debugging
    [ ] procrastinating
    [ ] emotionally attached to legacy code
    [x] all of the above
	
╔══════════════════════════════════════════════════════════════╗
  TO BE CONTINUED...
╚══════════════════════════════════════════════════════════════╝
*/











































/*
 * @TODO: Figure out why the plugin only breaks when Gary is near production.
 */