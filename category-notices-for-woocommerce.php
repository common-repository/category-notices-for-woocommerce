<?php
/*
 * Plugin Name: Category Notices for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/category-notices-for-woocommerce/
 * Description: Adds a notice to specific WooCommerce categories.
 * Version: 1.0.5
 * Author: Poly Plugins
 * Author URI: https://www.polyplugins.com
 */

namespace PolyPlugins;

if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, array(__NAMESPACE__ . '\WooCommerce_Category_Notices', 'install'));

class WooCommerce_Category_Notices
{

  private $plugin;
  private $plugin_name;
  private $plugin_menu_name;
  private $plugin_options_name;
  private $plugin_path;
  private $plugin_slug;
  private $plugin_slug_id;
  private $is_pro;
  private $support;
  private $woocommerce_category_notices_options;

  public static function install()
  {
    if (self::activation_check()) {
      add_option('category_notices_for_woocommerce');
    } else {
      deactivate_plugins(plugin_basename( __FILE__ ));
      wp_die( __('Category Notices for WooCommerce failed to activate, because multisite is not currently supported. This is planned in on our <a href="https://trello.com/b/yCyf2WYs/free-product-redirection-for-woocommerce" target="_blank">Roadmap</a>.', 'product-redirection-for-woocommerce' ));
    }
  }

  public function __construct() {
		// Define Properties
    $this->plugin = __FILE__;
    $this->plugin_name = 'Category Notices for WooCommerce';
    $this->plugin_menu_name = 'Category Notices';
    $this->plugin_options_name = 'category_notices_for_woocommerce';
    $this->plugin_path = plugin_dir_path(dirname($this->plugin));
    $this->plugin_slug = dirname(plugin_basename($this->plugin));
    $this->plugin_slug_id = str_replace('-', '_', $this->plugin_slug);
    $this->is_pro = (is_dir($this->plugin_path . $this->plugin_slug . '/pro')) ? true : false;
    if ($this->is_pro) {
      $this->support = " <a href='https://www.polyplugins.com/support/' target='_blank'>Get Support</a>";
    } else {
      $this->support = " <a href='https://wordpress.org/support/plugin/" . $this->plugin_slug . "/' target='_blank'>Get Support</a>";
    }
	}

  public function init()
  {
    // Display notice if incompatible
    add_action( 'admin_init', array( $this, 'check_compatibility' ) );

    // Don't run if incompatible
    if (!self::compatibility()) {
      return;
    }
    
    add_action('woocommerce_before_shop_loop', array($this, 'category_notice'));
    if ($this->is_pro) {
      add_action('woocommerce_after_checkout_validation', array($this, 'checkout_restrictions'), 10, 2);
    }
    add_action('admin_menu', array($this, 'add_plugin_page'));
    add_action('admin_init', array($this, 'page_init'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
    add_action('plugin_row_meta', array($this, 'plugin_meta_links'), 10, 4);

  }

  public function enqueue_scripts()
  {
    $page = (isset($_GET['page'])) ? sanitize_key($_GET['page']) : '';
    if ($page == $this->plugin_slug) {
      if ($this->is_pro) {
        wp_enqueue_script($this->plugin_slug, plugins_url('/pro/js/admin.js', __FILE__), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__)) . dirname(plugin_basename(__FILE__))  . '/pro/js/admin.js'), true);
      } else {
        wp_enqueue_script($this->plugin_slug, plugins_url('/js/admin.js', __FILE__), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__)) . dirname(plugin_basename(__FILE__))  . '/js/admin.js'), true);
      }
      wp_enqueue_style($this->plugin_slug, plugins_url('/css/style.css', __FILE__), array(), filemtime(plugin_dir_path(dirname(__FILE__)) . dirname(plugin_basename(__FILE__)) . '/css/style.css'));
    }
  }

  public function category_notice()
  {
    if (is_product_category()) {
      $this->woocommerce_category_notices_options = get_option($this->plugin_options_name);

      $notices = $this->woocommerce_category_notices_options['notices'];
      foreach ($notices as $notice) {
        $get_categories = explode(',', $notice['categories']);
        $categories = array_map('trim', $get_categories);
        foreach ($categories as $category) {
          if (is_product_category($category) && !empty($notice['notice'])) {
            $this->woocommerce_notice($notice['notice']);
          }
        }
      }
    }
  }

  public function checkout_restrictions($fields, $errors)
  {
    include_once($this->plugin_path . $this->plugin_slug . '/pro/inc/checkout-restrictions-method.php');
  }

  public function add_plugin_page()
  {
    add_submenu_page(
      'edit.php?post_type=product', // parent_slug
      $this->plugin_name, // page_title
      $this->plugin_menu_name, // menu_title
      'manage_options', // capability
      $this->plugin_slug, // menu_slug
      array($this, 'create_admin_page'), // function
      3 // after categories
    );
  }

  public function create_admin_page()
  {
    $this->woocommerce_category_notices_options = get_option($this->plugin_options_name); ?>

    <div class="wrap">
      <h2>WooCommerce Category Notices</h2>
      <p></p>
      <?php settings_errors(); ?>

      <form method="post" action="options.php">
        <?php
        settings_fields($this->plugin_slug_id . '_option_group');
        do_settings_sections($this->plugin_slug . '-admin');
        submit_button();
        ?>
      </form>
    </div>
  <?php
  }

  public function page_init()
  {
    register_setting(
      $this->plugin_slug_id . '_option_group', // option_group
      $this->plugin_options_name, // option_name
      array($this, 'sanitize') // sanitize_callback
    );

    add_settings_section(
      $this->plugin_slug_id . '_setting_section', // id
      '', // title
      array($this, 'section_info'), // callback
      $this->plugin_slug . '-admin' // page
    );

    add_settings_field(
      'categories', // id
      '', // title
      array($this, 'notices_callback'), // callback
      $this->plugin_slug . '-admin', // page
      $this->plugin_slug_id . '_setting_section' // section
    );
  }

  public function sanitize($input)
  {
    if (isset($input)) {
      $sanitary_values = array();
      
      $allowed_html =  array(
        'a' => array(
          'href' => array(),
          'title' => array(),
          'style' => array(),
        ),
        'em' => array(),
        'strong' => array(),
        'span' => array(
          'style' => array(),
        ),
      );

      foreach ($input as $option_key => $option) {
        if (!empty($option['categories'])) {
          $sanitary_values['notices'][$option_key]['categories'] = sanitize_text_field($option['categories']);
        }
        if (!empty($option['states']) && $this->is_pro) {
          $sanitary_values['notices'][$option_key]['states'] = sanitize_text_field($option['states']);
        }
        if (!empty($option['notice'])) {
          $sanitary_values['notices'][$option_key]['notice'] = wp_kses($option['notice'], $allowed_html);
        }
      }
      return $sanitary_values;
    }
  }

  public function section_info()
  {
  }

  public function notices_callback()
  {
    $options = (!empty($this->woocommerce_category_notices_options)) ? $this->woocommerce_category_notices_options : '';

    echo '<div class="category-notices-container">';
      if (!empty($options)) {
        foreach ($options['notices'] as $option_count => $option) {
          $notice_count = $option_count + 1;
          $categories = (isset($option['categories'])) ? $option['categories'] : '';
          $states = (isset($option['states'])) ? $option['states'] : '';
          $notice = (isset($option['notice'])) ? $option['notice'] : '';
          ?>
          <div class="category-notices">
            <div class="category-notices-title">
              NOTICE <?php echo ($this->is_pro) ? $notice_count : ''; ?>
            </div>
            <input class="regular-text categories" type="text" name="category_notices_for_woocommerce[<?php echo $option_count; ?>][categories]" value="<?php echo $categories; ?>" placeholder="Enter the category slug(s) Ex: t-shirts, shoes, glasses">
            <?php if ($this->is_pro) { ?>
              <input class="regular-text states" type="text" name="category_notices_for_woocommerce[<?php echo $option_count; ?>][states]" value="<?php echo $states; ?>" placeholder="If you want to block states from checking out in this category enter them here. Ex: GA, FL, TN">
            <?php } ?>
            <textarea class="regular-text category-notice" type="text" name="category_notices_for_woocommerce[<?php echo $option_count; ?>][notice]" placeholder="Enter the notice you would like to show on the above categories. <?php echo ($this->is_pro) ? " This will also be the error message on checkout if you restrict states." : ''; ?>"><?php echo $notice; ?></textarea>
          </div>
          <?php
        }
        if ($this->is_pro) {
        ?>
          <div class="add-remove-notices">
            <button class="button button-primary add-notice">Add Notice</button>    <button class="button button-primary delete-notice">Delete Last Notice</button>
          </div>
        <?php } else { ?>
          <div class="add-remove-notices"><a class="button button-primary" href="https://www.polyplugins.com/product/category-enhancements-for-woocommerce/" target="_blank">ADD MORE WITH PRO</a></div>
        <?php
        }
      } else {
        ?>
        <div class="category-notices">
          <div class="category-notices-title">NOTICE 1</div>
          <input class="regular-text categories" type="text" name="category_notices_for_woocommerce[0][categories]" placeholder="Enter the category slug(s) Ex: t-shirts, shoes, glasses">
          <?php if ($this->is_pro) { ?>
            <input class="regular-text states" type="text" name="category_notices_for_woocommerce[0][states]" placeholder="If you want to block states from checking out in this category enter them here. Ex: GA, FL, TN">
          <?php } ?>
          <textarea class="regular-text category-notice" type="text" name="category_notices_for_woocommerce[0][notice]" placeholder="Enter the notice you would like to show on the above categories. <?php echo ($this->is_pro) ? " This will also be the error message on checkout if you restrict states." : ''; ?>"></textarea>
        </div>
        <?php if ($this->is_pro) { ?>
          <div class="add-remove-notices">
            <button class="button button-primary add-notice">Add Notice</button>    <button class="button button-primary delete-notice" disabled>Delete Last Notice</button>
          </div>
        <?php } else { ?>
          <div class="add-remove-notices">
            <a class="button button-primary" href="https://www.polyplugins.com/product/category-enhancements-for-woocommerce/" target="_blank">ADD MORE WITH PRO</a>
          </div>
        <?php
        }
      }
    echo '</div>';
  }

  public static function activation_check() {
    if (is_multisite()) {
      return false;
    } else {
      return true;
    }
  }

  public function check_compatibility() {
    if ( ! self::compatibility() ) {
      if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
        add_action( 'admin_notices', array( $this, 'incompatible' ) );
      }
    }
  }

  public static function compatibility() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      return false;
    } else if (is_multisite()) {
      return false;
    } else {
      return true;
    }
  }

  public function incompatible() {
    $class = 'notice notice-error';

    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      $message = __('Category Notices for WooCommerce is not running, because <a href="plugin-install.php?s=WooCommerce&tab=search&type=term">WooCommerce</a> is not installed or activated.', 'product-redirection-for-woocommerce' );

      printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    }
    
    if (is_multisite()) {
      $message = __('Category Notices for WooCommerce is not running, because multisite is not supported. This is planned is on our <a href="https://trello.com/b/yCyf2WYs/free-product-redirection-for-woocommerce" target="_blank">Roadmap</a>.', 'product-redirection-for-woocommerce' );

      printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    }
  }

  public function plugin_action_links($links)
  {
    $settings_cta = '<a href="' . admin_url('/edit.php?post_type=product&page=' . $this->plugin_slug) . '" style="color: orange; font-weight: 700;">Settings</a>';
    if (!$this->is_pro){
      $pro_cta = '<a href="https://www.polyplugins.com/product/category-enhancements-for-woocommerce/" style="color: green; font-weight: 700;" target="_blank">Go Pro</a>';
      array_unshift($links, $settings_cta, $pro_cta);
    } else {
    array_unshift($links, $settings_cta);
    }
    return $links;
  }

  public function plugin_meta_links($links, $plugin_base_name)
  {
    if ($plugin_base_name === plugin_basename(__FILE__)) {
      if ($this->is_pro){
        $links[] = '<a href="https://www.polyplugins.com/support/" style="font-weight: 700;" target="_blank">Support</a>';
      } else {
        $links[] = '<a href="https://wordpress.org/support/plugin/category-notices-for-woocommerce/" style="font-weight: 700;" target="_blank">Support</a>';
      }
    }

    return $links;
  }

  public static function woocommerce_notice($message)
  {
    printf('<div class="woocommerce-notices-wrapper"><ul class="woocommerce-error" role="alert">%1$s</ul></div>', $message);
  }

}

$woocommerce_category_notices = new WooCommerce_Category_Notices;
$woocommerce_category_notices->init();
