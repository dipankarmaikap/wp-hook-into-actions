<?php

/**
 * Plugin Name: WP Hook Into Action
 * Description: Trigger a hook when an action is triggered.
 * Version: 0.1.3
 * Author:      Dipankar Maikap
 * Author URI:  https://dipankarmaikap.com/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 */
function whia_settings_page()
{
    add_submenu_page(
        'options-general.php', // top level menu page
        'Hook Options', // title of the settings page
        'WP Hook Options', // title of the submenu
        'manage_options', // capability of the user to see this page
        'wp-hook-options', // slug of the settings page
        'wp_hook_option_page_html' // callback function to be called when rendering the page
    );
    add_action('admin_init', 'whia_settings_init');
}
add_action('admin_menu', 'whia_settings_page');
function whia_settings_init()
{
    add_settings_section(
        'wp-hook-settings-section', // id of the section
        'WP Hook Options', // title to be displayed
        '', // callback function to be called when opening section
        'wp-hook-options' // page on which to display the section, this should be the same as the slug used in add_submenu_page()
    );

    // register the setting
    register_setting(
        'wp-hook-options', // option group
        'whia_rest_endpoint' // option name
    );

    add_settings_field(
        'whia-rest-endpoint', // id of the settings field
        'Rest api endpoint', // title
        'whia_settings_cb', // callback function
        'wp-hook-options', // page on which settings display
        'wp-hook-settings-section' // section on which to show settings
    );
}
function whia_settings_cb()
{
    $restEndpoint = esc_attr(get_option('whia_rest_endpoint', ''));
?>
    <div id="whia-option-form">
        <span style="display: block;opacity: 0.6;">Eg:http://localhost:3000/api/revalidate?secret=mysecrettoken</span>
        <input id="whia_rest_endpoint" type="text" name="whia_rest_endpoint" value="<?php echo $restEndpoint; ?>">
    </div>
<?php
}
function wp_hook_option_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }
?>
    <div class="wrap">
        <form method="POST" action="options.php">
            <?php settings_fields('wp-hook-options'); ?>
            <?php do_settings_sections('wp-hook-options') ?>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}
add_action('wp_trash_post', 'send_post_delete_event');
add_action('save_post_post', 'send_post_update_event');
add_action('save_post_page', 'send_post_update_event');
if (class_exists('ACF')) {
    add_action('acf/save_post', 'send_post_update_event');
}

function wsraT_get_all_postTypes()
{
    $args = array(
        'exclude_from_search' => false
    );
    $argsTwo = array(
        'publicly_queryable' => true
    );
    $post_types = get_post_types($args, 'names');
    $post_typesTwo = get_post_types($argsTwo, 'names');
    $post_types = array_merge($post_types, $post_typesTwo);
    unset($post_types['attachment']);
    return $post_types;
}

function send_post_delete_event($post_id)
{
    if (wp_is_post_autosave($post_id)) {
        return;
    }
    $restEndpoint = esc_attr(get_option('whia_rest_endpoint', ''));
    $isEmpty = empty($restEndpoint);
    if ($isEmpty) {
        return;
    }
    if (!(defined('REST_REQUEST') && REST_REQUEST)) {

        $post = get_post($post_id);
        $post_types = wsraT_get_all_postTypes();
        if (in_array($post->post_type, $post_types)) {
            $isPublished =   true;
            if (!$isEmpty && $isPublished) {
                return post_array_to_remote($restEndpoint, $post_id, $post, 'delete');
            }
        }
    }
}
function send_post_update_event($post_id)
{
    if (wp_is_post_autosave($post_id)) {
        return;
    }
    $restEndpoint = esc_attr(get_option('whia_rest_endpoint', ''));
    $isEmpty = empty($restEndpoint);
    if ($isEmpty) {
        return;
    }
    if (!(defined('REST_REQUEST') && REST_REQUEST)) {

        $post = get_post($post_id);
        $post_types = wsraT_get_all_postTypes();
        if (in_array($post->post_type, $post_types)) {
            $isPublished =  $post->post_status == 'publish';
            if (!$isEmpty && $isPublished) {
                return post_array_to_remote($restEndpoint, $post_id, $post, 'publish');
            }
        }
    }
}

function post_array_to_remote($url, $post_id, $post, $t)
{
    $body = [
        "url" => str_replace(home_url(), '', get_permalink($post_id)),
        "type" => $post->post_type,
        "slug" =>  $post->post_name,
        "post_id" => $post_id,
        'status' => $t,
    ];
    $body = wp_json_encode($body);
    $options = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
        ],
    ];
    $response = wp_remote_post($url, $options);
    return $response;
}
