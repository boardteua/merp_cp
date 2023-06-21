<?php

/*
  Plugin Name: Membership Custom Price
  Plugin URI:  #
  Description: Add ability to set custom membership price for users.
  Version: 1.0
  Author: org100h
  Author URI: #
  License: GPLv2
 */

defined('\ABSPATH') || die('No direct script access allowed!');

if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

class merp_cp
{

    /**
     * The unique instance of the plugin.
     *
     * @var org_custom_shortcode
     */
    private static $instance = null;

    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_filter('mepr_adjusted_price', [$this, "merp_cp_adjust_price"], 3, 30);
        add_filter('mepr-admin-subscriptions-cols', [$this, "mepr_admin_subscriptions_custom_price_col"], 3, 30);

        add_action('show_user_profile', [$this, 'mepr_user_profile_fields'], 30);
        add_action('edit_user_profile', [$this, 'mepr_user_profile_fields'], 30);

        add_action('personal_options_update', [$this, 'mepr_save_custom_user_profile_fields'], 30);
        add_action('edit_user_profile_update', [$this, 'mepr_save_custom_user_profile_fields'], 30);
        add_action('mepr-admin-subscriptions-cell', [$this, 'mepr_add_admin_subscriptions_cell'], 10, 4);
    }

    /**
     * Return collection of memberships
     *
     */
    private function merp_get_memberships()
    {
        $args = [
            'post_type' => 'memberpressproduct',
            'posts_per_page' => -1, // to retrieve all memberships
        ];
        return get_posts($args) ?? false;
    }

    /**
     * Show price fields on user profile page
     *
     */
    public function mepr_user_profile_fields($user)
    {
        if (current_user_can('administrator')) { ?>
            <h3><?php _e('Custom Membership Price', 'mepr'); ?></h3>
            <table class="form-table">
                <?php
                $memberships = $this->merp_get_memberships();
                foreach ($memberships ?? [] as $membership) {
                    ?>
                    <tr>
                        <th>
                            <label for="price">
                                <?php _e('Price ', 'mepr'); ?>
                                <?php echo esc_attr($membership->post_title) ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($membership->ID) ?>_price" id="price"
                                   value="<?php echo esc_attr(get_the_author_meta($membership->ID . '_price', $user->ID)); ?>"
                                   class="regular-text"/>
                        </td>
                    </tr>
                    <?php
                }
                ?> </table>
            <?php
        }
    }

    /**
     * Sanitize and save price fields
     *
     */
    public function mepr_save_custom_user_profile_fields($user_id)
    {
        if (current_user_can('administrator')) {
            $memberships = $this->merp_get_memberships();
            foreach ($memberships ?? [] as $membership) {
                update_user_meta($user_id, $membership->ID . '_price', sanitize_text_field($_POST[$membership->ID . '_price']));
            }
        }
    }

    /**
     * Filter memberships prices
     *
     */
    public function merp_cp_adjust_price($product_price, $coupon_code, $this_)
    {
        $id = get_current_user_id();
        $price = (float)get_the_author_meta($this_->rec->ID . '_price', $id);
        if ($id && $price > 0) {
            $product_price = $price;
        }
        return $product_price;
    }

    /**
     * Add Custom row with user price for Subscription Dashboard
     *
     */

    public function mepr_admin_subscriptions_custom_price_col($cols, $prefix, $lifetime)
    {
        $cols[$prefix . 'custom_price'] = __('User Price', 'memberpress');
        return $cols;
    }

    /**
     * Add Custom Cells with user price
     *
     */

    function mepr_add_admin_subscriptions_cell($column_name, $rec, $table, $attributes)
    {
        $user = new MeprUser($rec->user_id);
        $price = get_user_meta($user->ID, $rec->product_id . '_price', true);
        if (false !== str_contains($column_name, '_custom_price') && (int)$user->ID > 0) {
            ?>
            <td <?php echo $attributes; ?>>
                <?php echo MeprAppHelper::format_currency($price, true, false); ?>
            </td>
            <?php
        }
    }
}

if (is_plugin_active('memberpress/memberpress.php')) {
    $merp_cp_plugin = merp_cp::get_instance();
}