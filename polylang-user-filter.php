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

// Add custom Country Editor role
function puf_add_country_editor_role()
{
    $role = get_role('translator');
    if ($role) {
        $caps = [
            'edit_posts',
            'edit_pages',
            'edit_others_posts',
            'edit_others_pages',
            'edit_published_posts',
            'edit_published_pages',
            'publish_posts',
            'publish_pages',
            'delete_published_posts',
            'delete_other_posts',
            'delete_posts',
            'delete_pages',
            'upload_files'
        ];
        foreach ($caps as $cap) {
            if ($role->has_cap($cap)) continue;

            $role->add_cap($cap);
        }
    }
}

add_action('init', 'puf_add_country_editor_role');
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

function get_user_languages_or_all($args) {

    if (current_user_can('administrator')) {
        return true;
    }

    $current_user = wp_get_current_user();
    $user_languages = get_user_meta($current_user->ID, 'user_languages', true);
    if (isset($args['query'])) {
        $query = $args['query'];
        $post_type = $query->query['post_type'];
        $is_main_query = $query->is_main_query();
    }
    if (isset($args['post_type'])) {
        $post_type = $args['post_type'];
        $is_main_query = true;
    }

    if (is_admin() && !empty($user_languages) && is_array($user_languages) && $is_main_query && (isset($post_type) && in_array($post_type, array('post', 'page', 'influencer')))) {
        return $user_languages;
    }

    return true;
}

function render_veggie_challenge_language_limitations_meta_box_content($post, $args) 
{
    $user_languages = get_user_languages_or_all(array('post_type' => $post->post_type));
    if ($user_languages !== true) {
    ?>
        <div>You can see the following languages: <?php echo implode(',', $user_languages) ?></div>
        <script>
            jQuery(document).ready(function() {// Array of allowed values
                const allowedLanguages = ["<?php echo implode('","', $user_languages) ?>"];
                
                // Select the <select> element by its ID
                jQuery("#post_lang_choice option").each(function () {
                    // Check if the option's value is not in the allowedLanguages array
                    if (!allowedLanguages.includes(jQuery(this).val())) {
                        jQuery(this).remove(); // Remove the option
                    }
                });

                jQuery("#post-translations table tr").each(function () {
                    // Find the hidden input within the current row
                    const hiddenInput = jQuery(this).find('input[type="hidden"]');

                    if (hiddenInput.length > 0) {
                        // Extract the language code from the name attribute (e.g., "post_tr_lang[nl]")
                        const match = hiddenInput.attr("name").match(/\[(.+?)\]/);
                        const languageCode = match ? match[1] : null;

                        // If the language code is not in the allowed languages, remove the row
                        if (!allowedLanguages.includes(languageCode)) {
                            jQuery(this).remove();
                        }
                    }
                });
            });
        </script>
    <?php
    } else {
    ?>
        <div>You can see all languages</div>
    <?php
    }
}
 
function add_veggie_challenge_language_limitations_meta_box( $post_type ) {

    add_meta_box( 
        'veggie_challenge_language_limitations'
       ,'Veggie Challenge language limitations'
       ,'render_veggie_challenge_language_limitations_meta_box_content'
       ,null 
       ,'side'
       ,'high');
}

add_filter( 'add_meta_boxes', 'add_veggie_challenge_language_limitations_meta_box' );

// Filter posts and pages by user's languages in the admin area
function puf_filter_content_by_language($query)
{
    $user_languages = get_user_languages_or_all(array('query' => $query));
    if ($user_languages !== true) {
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

add_action('admin_footer-edit.php', 'remove_language_column');

function remove_language_column() {
    global $typenow;

    if (current_user_can('administrator')) {
        return;
    }

    if ($typenow === 'page') {
        ?>
        <script>
        // Your custom JS here
        document.querySelectorAll("[class*='column-language_']").forEach(el => el.remove()); 
        </script>
        <?php
    }
}