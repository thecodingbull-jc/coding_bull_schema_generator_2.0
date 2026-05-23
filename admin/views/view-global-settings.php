<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'tcb_schema';

// Load all saved settings for this page
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT property, value FROM $table_name WHERE page = %s", 'global'),
    OBJECT_K
);
$saved_settings = [];
if ($results) {
    foreach ($results as $property => $row) {
        $saved_settings[$property] = $row->value;
    }
}
//var_dump($saved_settings);
// Get post types and taxonomies
$post_types = get_post_types([], 'objects');
$taxonomies = get_taxonomies([], 'objects');

//select post manually
function tcb_schema_posts_multiselect( $selected_values = [] , $div_id) {
    $posts = get_posts([
        'post_type' => 'any',
        'posts_per_page' => -1
    ]);
    echo '<div style="height:100px; overflow:auto; background:white; border-radius:8px; padding:20px;" id="' . $div_id . '">';
    foreach( $posts as $p ) {
        $checked = in_array($p->ID, $selected_values) ? 'checked' : '';
        echo '<label><input type="checkbox" name="tcb_selected_posts[]" value="'.esc_attr($p->ID).'" '.$checked.'> '.esc_html($p->post_title).'</label><br>';
    }
    echo '</div>';
}

function tcb_schema_get_selected_posts($property_name) {
    global $wpdb;

    $row = $wpdb->get_row("
        SELECT value 
        FROM wp_tcb_schema 
        WHERE property = '" . $property_name . " '
        AND page = 'global'
        LIMIT 1
    ");

    if ( $row && ! empty( $row->value ) ) {
        $clean_json = stripslashes($row->value);
        $arr = json_decode($clean_json, true);
        return $arr;
    }

    return []; // no values yet
}

?>
<h2>Global Settings</h2>
<div style="display:flex;">
    <div style="width:50%;">
        <div class="schema-generator-section" style="display:flex; flex-direction:column; gap:20px;">
            

            <div>
                <input type="checkbox" id="schema-generator-single-location" name="schema-single-location"
                    <?php checked( !empty($saved_settings['single_location']) ); ?> />
                <label><b>Single Location</b> If checked, no service_area related schema will show. "Local Business" tab will ask for the business address info.</label>
            </div>
            
            <div>
                <h4>Page Type Settings</h4>

                <div  id="schema-generator-service-area-definition-container" style="margin-bottom:20px; display:flex; align-items:center;">
                    <label>Service Area Pages Definition:</label>
                    <select id="schema-generator-service-area-page-definition" name="schema-generator-service-area-page-definition">
                        <option value="">Select a post type</option>
                        <?php foreach ($post_types as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_area_posttype'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="schema-generator-service-area-page-definition-taxonomy" name="schema-generator-service-area-page-definition-taxonomy">
                        <option value="">Select a taxonomy</option>
                        <?php foreach ($taxonomies as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_area_taxonomy'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="schema-generator-service-area-page-definition-term" name="schema-generator-service-area-page-definition-term" disabled>
                        <option value="">Select a term</option>
                    </select>
                </div>

                <div  id="schema-generator-service-area-taxonomy-container" style="margin-bottom:20px;">
                    <label>Service Area Taxonomy:</label>
                    <select id="schema-generator-service-area-taxonomy" name="schema-generator-service-area-taxonomy">
                        <option value="">Select a taxonomy</option>
                        <?php foreach ($taxonomies as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_area_taxonomy_slug'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div  style="margin-bottom:20px;">
                    <label for="schema-generator-service-general-page-definition">Service General Pages Definition: </label>
                    <select id="schema-generator-service-general-page-definition" name="schema-generator-service-general-page-definition">
                        <option value="">Select a post type</option>
                        <?php foreach ($post_types as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_general_posttype'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="schema-generator-service-general-page-definition-taxonomy" name="schema-generator-service-general-page-definition-taxonomy">
                        <option value="">Select a taxonomy</option>
                        <?php foreach ($taxonomies as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_general_taxonomy'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="schema-generator-service-general-page-definition-term" name="schema-generator-service-general-page-definition-term" disabled>
                        <option value="">Select a term</option>
                    </select>
                </div>

                <div style="margin-bottom:20px;">
                    <label>Service Capability Pages Definition:</label>
                    <select id="schema-generator-service-capability-page-definition" name="schema-generator-service-capability-page-definition">
                        <option value="">Select a post type</option>
                        <?php foreach ($post_types as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_capability_posttype'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="schema-generator-service-capability-page-definition-taxonomy" name="schema-generator-service-capability-page-definition-taxonomy">
                        <option value="">Select a taxonomy</option>
                        <?php foreach ($taxonomies as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_capability_taxonomy'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="schema-generator-service-capability-page-definition-term" name="schema-generator-service-capability-page-definition-term" disabled>
                        <option value="">Select a term</option>
                    </select>
                </div>

                <div>
                    <label>Service Taxonomy:</label>
                    <select id="schema-generator-service-taxonomy" name="schema-generator-service-taxonomy">
                        <option value="">Select a taxonomy</option>
                        <?php foreach ($taxonomies as $slug => $obj): ?>
                            <option value="<?php echo esc_attr($slug); ?>"
                                <?php selected($saved_settings['service_taxonomy_slug'] ?? '', $slug); ?>>
                                <?php echo esc_html($obj->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
            </div>

            <div>
                <label>Review Pages Definition: </label>
                <select id="schema-generator-review-page-definition" name="schema-generator-review-page-definition">
                    <option value="">Select a post type</option>
                    <?php foreach ($post_types as $slug => $obj): ?>
                        <option value="<?php echo esc_attr($slug); ?>"
                            <?php selected($saved_settings['review_posttype'] ?? '', $slug); ?>>
                            <?php echo esc_html($obj->labels->singular_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Employee Pages Definition: </label>
                <select id="schema-generator-employee-page-definition" name="schema-generator-employee-page-definition">
                    <option value="">Select a post type</option>
                    <?php foreach ($post_types as $slug => $obj): ?>
                        <option value="<?php echo esc_attr($slug); ?>"
                            <?php selected($saved_settings['employee_posttype'] ?? '', $slug); ?>>
                            <?php echo esc_html($obj->labels->singular_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Past Project Pages Definition: </label>
                <select id="schema-generator-past-project-page-definition" name="schema-generator-past-project-page-definition">
                    <option value="">Select a post type</option>
                    <?php foreach ($post_types as $slug => $obj): ?>
                        <option value="<?php echo esc_attr($slug); ?>"
                            <?php selected($saved_settings['past_project_posttype'] ?? '', $slug); ?>>
                            <?php echo esc_html($obj->labels->singular_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <h4>Web Agency Credit</h4>
                <p style="font-size:13px; color:#555; margin-bottom:10px;">
                    When name and URL are set, a <code>WebSite</code> schema with a <code>creator</code> property will be output on the front page to credit the agency that designed and built this site.
                </p>
                <div style="margin-bottom:10px;">
                    <label for="schema-generator-agency-name"><b>Web Agency Name:</b></label><br>
                    <input type="text" id="schema-generator-agency-name" name="schema-generator-agency-name"
                           value="<?php echo esc_attr($saved_settings['agency_name'] ?? 'Coding Bull - Web Design Company & Digital Marketing Agency'); ?>"
                           style="width:400px;" />
                </div>
                <div style="margin-bottom:10px;">
                    <label for="schema-generator-agency-url"><b>Web Agency URL:</b></label><br>
                    <input type="text" id="schema-generator-agency-url" name="schema-generator-agency-url"
                           value="<?php echo esc_attr($saved_settings['agency_url'] ?? 'https://thecodingbull.com/'); ?>"
                           style="width:400px;" />
                </div>
                <div style="margin-bottom:10px;">
                    <label for="schema-generator-agency-description"><b>Web Agency Description:</b></label><br>
                    <input type="text" id="schema-generator-agency-description" name="schema-generator-agency-description"
                           value="<?php echo esc_attr($saved_settings['agency_description'] ?? 'We help service-based businesses fill up their sales pipeline by getting their website design & digital marketing right.'); ?>"
                           style="width:400px;" />
                </div>
            </div>

            <div>
                <h4>How To Make Different Content Show In Different Schema</h4>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">🏠 Homepage Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #3b82f6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Employee appears on homepage schema when:</strong><br>
                            All published employee posts appear — no taxonomy filter applied.
                        </div>
                        <div style="background:white; border-left:3px solid #f59e0b; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Review appears on homepage schema when:</strong><br>
                            All published review posts appear as aggregateRating — no taxonomy filter applied.
                        </div>
                        <div style="background:white; border-left:3px solid #10b981; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Service area appears on homepage schema when:</strong><br>
                            Service area posts with the configured service area taxonomy term appear as <em>areaServed</em>.
                            Only applies when <strong>Single Location</strong> is unchecked — if checked, the homepage address is used instead.
                        </div>
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Blog articles, service general pages, service capability pages, and past projects do NOT appear on homepage schema.</strong>
                        </div>
                    </div>
                </details>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">📍 Service Area Page Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #3b82f6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Employee appears on service area page schema when:</strong><br>
                            The employee post shares <em>at least one</em> service area taxonomy term with the service area page.
                        </div>
                        <div style="background:white; border-left:3px solid #f59e0b; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Review appears on service area page schema when:</strong><br>
                            The review post shares <em>at least one</em> service area taxonomy term with the service area page.
                        </div>
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Blog articles, service general pages, service capability pages, and past projects do NOT appear on service area page schema.</strong>
                        </div>
                    </div>
                </details>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">🛠️ Service General Page Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #3b82f6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Capability page appears on service general page schema when:</strong><br>
                            The capability post shares <em>at least one</em> service area taxonomy term with the general page
                            <strong>AND</strong> has the capability taxonomy term set in global settings.
                        </div>
                        <div style="background:white; border-left:3px solid #10b981; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Blog article appears on service general page schema when:</strong><br>
                            The blog post shares the same <em>service taxonomy</em> term as the general page.
                        </div>
                        <div style="background:white; border-left:3px solid #f59e0b; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Review appears on service general page schema when:</strong><br>
                            The review post shares the same <em>service area taxonomy</em> term
                            <strong>AND</strong> the same <em>service taxonomy</em> term as the general page.
                        </div>
                        <div style="background:white; border-left:3px solid #8b5cf6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Past project appears on service general page schema when:</strong><br>
                            The past project shares the same <em>service taxonomy</em> term
                            <strong>AND</strong> the same <em>service area taxonomy</em> term as the general page.
                        </div>
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Employee pages do NOT appear on service general page schema.</strong>
                        </div>
                    </div>
                </details>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">⚙️ Service Capability Page Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #f59e0b; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Review appears on service capability page schema when:</strong><br>
                            The review post shares <em>at least one</em> service area taxonomy term
                            <strong>AND</strong> at least one service taxonomy term with the capability page.
                        </div>
                        <div style="background:white; border-left:3px solid #10b981; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Blog article appears on service capability page schema when:</strong><br>
                            The blog post shares the same <em>service taxonomy</em> term as the capability page.
                        </div>
                        <div style="background:white; border-left:3px solid #8b5cf6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Past project appears on service capability page schema when:</strong><br>
                            The past project shares the same <em>service taxonomy</em> term as the capability page.
                        </div>
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Employee pages do NOT appear on service capability page schema.</strong>
                        </div>
                    </div>
                </details>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">📝 Blog Page Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #3b82f6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Service general page or service capability page appears in blog page schema when:</strong><br>
                            The service post shares the same <em>service taxonomy</em> term as the blog post
                            <strong>AND</strong> is tagged as either a general or capability page type. These appear under <em>mentions</em>.
                        </div>
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Reviews, employees, and past projects do NOT appear on blog page schema.</strong>
                        </div>
                    </div>
                </details>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">📁 Past Project Page Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #3b82f6; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Service general page or service capability page appears in past project page schema when:</strong><br>
                            The service post shares the same <em>service taxonomy</em> term
                            <strong>AND</strong> the same <em>service area taxonomy</em> term as the past project. These appear under <em>about</em>.
                        </div>
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Reviews, blog articles, and employees do NOT appear on past project page schema.</strong>
                        </div>
                    </div>
                </details>

                <details style="margin-bottom:10px; background:#f0f6fc; border:1px solid #b3d4f5; border-radius:6px; padding:12px 16px;">
                    <summary style="cursor:pointer; font-weight:600; color:#1d4ed8;">👤 Employee Page Schema</summary>
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px; font-size:13px; line-height:1.6;">
                        <div style="background:white; border-left:3px solid #6b7280; padding:8px 12px; border-radius:0 4px 4px 0;">
                            <strong>Employee pages do not have their own standalone schema.</strong><br>
                            Employee data appears within <em>Homepage</em> and <em>Service Area page</em> schemas instead.
                        </div>
                    </div>
                </details>
            </div>
            
        </div> 
    </div>
    <div style="width:50%;">
       
        <h4>Select Service Area Pages</h4>
        <? 
        $selected_posts = tcb_schema_get_selected_posts("manual_service_area_posts");
        tcb_schema_posts_multiselect( $selected_posts , "sc_select_service_area_pages" ); 
        ?>
        <h4>Select Service General Pages</h4>
        <? 
        $selected_posts = tcb_schema_get_selected_posts("manual_service_general_posts");
        tcb_schema_posts_multiselect( $selected_posts , "sc_select_service_general_pages" ); 
        ?>
        <h4>Select Service Capability Pages</h4>
        <? 
        $selected_posts = tcb_schema_get_selected_posts("manual_service_capability_posts");  
        tcb_schema_posts_multiselect( $selected_posts , "sc_select_service_capability_pages" ); 
        ?>
    </div>
</div>
<!-- Save Button -->
<button style="margin-top:20px;" id="schema-save-global-settings" class="button button-primary" type="button">Save Settings</button>

<script>
jQuery(document).ready(function($){
    //fetch terms when taxonomy on change(service area definitiaon)
    $('#schema-generator-service-area-page-definition-taxonomy').on('change', function(){
        const taxonomy = $(this).val();
        const termSelect = $('#schema-generator-service-area-page-definition-term');

        if(!taxonomy){
            termSelect.prop('disabled', true)
                      .empty()
                      .append('<option value="">Select a term</option>');
            return;
        }

        // loading
        termSelect.prop('disabled', true)
                  .empty()
                  .append('<option>Loading terms...</option>');

        // AJAX fetch terms
        $.post(ajaxurl, { action: 'get_terms_by_taxonomy', taxonomy }, function(response){
            termSelect.empty();

            if(response.success && Object.keys(response.data).length){
                termSelect.append('<option value="">Select a term</option>');
                $.each(response.data, function(term_id, term_name){
                    termSelect.append(`<option value="${term_id}">${term_name}</option>`);
                });
                termSelect.prop('disabled', false);
            } else {
                termSelect.append('<option value="">No terms found</option>');
            }
        });
    });
    //fetch terms when taxonomy on change(service general definition)
    $('#schema-generator-service-general-page-definition-taxonomy').on('change', function(){
        const taxonomy = $(this).val();
        const termSelect = $('#schema-generator-service-general-page-definition-term');

        if(!taxonomy){
            termSelect.prop('disabled', true)
                      .empty()
                      .append('<option value="">Select a term</option>');
            return;
        }

        // loading
        termSelect.prop('disabled', true)
                  .empty()
                  .append('<option>Loading terms...</option>');

        // AJAX fetch terms
        $.post(ajaxurl, { action: 'get_terms_by_taxonomy', taxonomy }, function(response){
            termSelect.empty();

            if(response.success && Object.keys(response.data).length){
                termSelect.append('<option value="">Select a term</option>');
                $.each(response.data, function(term_id, term_name){
                    termSelect.append(`<option value="${term_id}">${term_name}</option>`);
                });
                termSelect.prop('disabled', false);
            } else {
                termSelect.append('<option value="">No terms found</option>');
            }
        });
    });
    //fetch terms when taxonomy on change(service capability definition)
    $('#schema-generator-service-capability-page-definition-taxonomy').on('change', function(){
        const taxonomy = $(this).val();
        const termSelect = $('#schema-generator-service-capability-page-definition-term');

        if(!taxonomy){
            termSelect.prop('disabled', true)
                      .empty()
                      .append('<option value="">Select a term</option>');
            return;
        }

        // loading
        termSelect.prop('disabled', true)
                  .empty()
                  .append('<option>Loading terms...</option>');

        // AJAX fetch terms
        $.post(ajaxurl, { action: 'get_terms_by_taxonomy', taxonomy }, function(response){
            termSelect.empty();

            if(response.success && Object.keys(response.data).length){
                termSelect.append('<option value="">Select a term</option>');
                $.each(response.data, function(term_id, term_name){
                    termSelect.append(`<option value="${term_id}">${term_name}</option>`);
                });
                termSelect.prop('disabled', false);
            } else {
                termSelect.append('<option value="">No terms found</option>');
            }
        });
    });
    
    // Handle Save Button
    $('#schema-save-global-settings').on('click', function(){
        const data = {
            action: 'save_schema_global_settings',
            settings: {
                global_name: $('#schema-generator-global-name').val(),
                global_description: $('#schema-generator-global-description').val(),
                service_general_posttype: $('#schema-generator-service-general-page-definition').val(),
                single_location: $('#schema-generator-single-location').is(':checked') ? 1 : '',
                service_area_posttype: $('#schema-generator-service-area-page-definition').val(),
                service_area_taxonomy: $('#schema-generator-service-area-page-definition-taxonomy').val(),
                service_area_taxonomy_slug: $('#schema-generator-service-area-taxonomy').val(),
                service_taxonomy_slug: $('#schema-generator-service-taxonomy').val(),
                service_area_term: $('#schema-generator-service-area-page-definition-term').val(),
                service_general_taxonomy: $('#schema-generator-service-general-page-definition-taxonomy').val(),
                service_general_term: $('#schema-generator-service-general-page-definition-term').val(),
                service_capability_posttype: $('#schema-generator-service-capability-page-definition').val(),
                service_capability_taxonomy: $('#schema-generator-service-capability-page-definition-taxonomy').val(),
                service_capability_term: $('#schema-generator-service-capability-page-definition-term').val(),
                review_posttype: $('#schema-generator-review-page-definition').val(),
                employee_posttype: $('#schema-generator-employee-page-definition').val(),
                manual_service_area_posts:getSelectedPostsByDiv("sc_select_service_area_pages"),
                manual_service_general_posts:getSelectedPostsByDiv("sc_select_service_general_pages"),
                manual_service_capability_posts:getSelectedPostsByDiv("sc_select_service_capability_pages"),
                past_project_posttype: $('#schema-generator-past-project-page-definition').val(),
                agency_name:        $('#schema-generator-agency-name').val(),
                agency_url:         $('#schema-generator-agency-url').val(),
                agency_description: $('#schema-generator-agency-description').val(),
            }
        };
        console.log(data);
        $.post(ajaxurl, data, function(response){
            if(response.success){
                alert('✅ Saved successfully!');
                location.reload();
            } else {
                alert('❌ Error saving settings.');
            }
        });
    });

    // After page load, if saved taxonomy exists, auto-load terms and select saved one
    function loadSavedTerm(taxonomySelectId, termSelectId, savedTermId) {
        const taxonomy = $(taxonomySelectId).val();
        const termSelect = $(termSelectId);

        if (!taxonomy) return;

        termSelect.prop('disabled', true).html('<option>Loading terms...</option>');
        $.post(ajaxurl, { action: 'get_terms_by_taxonomy', taxonomy }, function(response){
            termSelect.empty();
            if(response.success && Object.keys(response.data).length){
                termSelect.append('<option value="">Select a term</option>');
                $.each(response.data, function(id, name){
                    const selected = (id == savedTermId) ? 'selected' : '';
                    termSelect.append(`<option value="${id}" ${selected}>${name}</option>`);
                });
                termSelect.prop('disabled', false);
            } else {
                termSelect.append('<option>No terms found</option>');
            }
        });
    }

    //toggle service area page on single location
    $('#schema-generator-single-location').on('change',function(){
        if(this.checked){
            $('#schema-generator-service-area-definition-container').css("display","none");
            $('#schema-generator-service-area-taxonomy-container').css("display","none");
        }else{
            $('#schema-generator-service-area-definition-container').css("display","flex");
            $('#schema-generator-service-area-taxonomy-container').css("display","block");
        }
        
    })

    

    // Run after DOM ready
    $(function(){
        const savedAreaTerm = "<?php echo esc_js($saved_settings['service_area_term'] ?? ''); ?>";
        const savedGeneralTerm = "<?php echo esc_js($saved_settings['service_general_term'] ?? ''); ?>";
        const savedCapabilityTerm = "<?php echo esc_js($saved_settings['service_capability_term'] ?? ''); ?>";
        if(savedAreaTerm){
            loadSavedTerm('#schema-generator-service-area-page-definition-taxonomy', '#schema-generator-service-area-page-definition-term', savedAreaTerm);
        }
        if(savedGeneralTerm){
            loadSavedTerm('#schema-generator-service-general-page-definition-taxonomy', '#schema-generator-service-general-page-definition-term', savedGeneralTerm);
        }
        if(savedCapabilityTerm){
            loadSavedTerm('#schema-generator-service-capability-page-definition-taxonomy', '#schema-generator-service-capability-page-definition-term', savedCapabilityTerm);
        }
        if( $('#schema-generator-single-location').prop('checked')){
            $('#schema-generator-service-area-definition-container').css("display","none");
            $('#schema-generator-service-area-taxonomy-container').css("display","none");
        }else{
            $('#schema-generator-service-area-definition-container').css("display","flex");
            $('#schema-generator-service-area-taxonomy-container').css("display","block");
        }
        hideLoading();
    });

    function getSelectedPostsByDiv(divId) {
        const container = document.getElementById(divId);
        if (!container) return '[]'; // if div not found, return empty array

        const checkedPosts = Array.from(
            container.querySelectorAll('input[name="tcb_selected_posts[]"]:checked')
        ).map(el => el.value); // get post IDs

        console.log('Selected posts in', divId, checkedPosts);

        return JSON.stringify(checkedPosts); // JSON string
    }
});
</script>