<?php
/**
 * Plugin Name: Polylang User Language Filter
 * Description: Adds a language selection to user profiles and filters posts/pages based on selected languages in the admin area.
 * Version: 1.1
 * Author: Ad van Wingerden
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add languages field to user profile, editable only by administrators
function puf_add_languages_field($user)
{
    if (!function_exists('pll_languages_list')) {
        return;
    }

    // Only allow administrators to edit the languages field
    if (!current_user_can('administrator')) {
        return;
    }

    $languages = pll_languages_list(array('fields' => 'slug'));
    $user_languages = get_user_meta($user->ID, 'user_languages', true);

    ?>
    <h3><?php _e('Languages', 'polylang-user-filter'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="user_languages"><?php _e('Select Languages', 'polylang-user-filter'); ?></label></th>
            <td>
                <select id="user_languages" name="user_languages[]" multiple="multiple" style="width: 100%;">
                    <?php foreach ($languages as $language): ?>
                        <option value="<?php echo esc_attr($language); ?>" <?php echo (is_array($user_languages) && in_array($language, $user_languages)) ? 'selected="selected"' : ''; ?>>
                            <?php echo esc_html($language); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Select the languages you understand.', 'polylang-user-filter'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('show_user_profile', 'puf_add_languages_field');
add_action('edit_user_profile', 'puf_add_languages_field');

// Save user languages, only if current user is administrator
function puf_save_user_languages($user_id)
{
    // Only allow administrators to save the languages field
    if (!current_user_can('administrator')) {
        return;
    }

    if (isset($_POST['user_languages'])) {
        update_user_meta($user_id, 'user_languages', array_map('sanitize_text_field', $_POST['user_languages']));
    } else {
        delete_user_meta($user_id, 'user_languages');
    }
}

add_action('personal_options_update', 'puf_save_user_languages');
add_action('edit_user_profile_update', 'puf_save_user_languages');

function puf_is_site_admin(){
    return in_array('administrator',  wp_get_current_user()->roles);
}

// Filter posts and pages by user's languages in the admin area
function puf_filter_content_by_language($query)
{
    $current_user = wp_get_current_user();
    $user_languages = get_user_meta($current_user->ID, 'user_languages', true);
    if (is_admin() && !empty($user_languages) && is_array($user_languages) && $query->is_main_query() && (isset($query->query['post_type']) && in_array($query->query['post_type'], array('post', 'page')))) {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'language',
            'field'    => 'slug',
            'terms'    => $user_languages,
        );
        $query->set('tax_query', $tax_query);
    }
}

add_action('pre_get_posts', 'puf_filter_content_by_language');
