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
                <label>Single Location</label>
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