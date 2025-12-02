<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$builtin_fields = array(
    'ID,built-in'           => 'ID',
    'post_title,built-in'   => 'Title',
    'post_content,built-in' => 'Content',
    'post_excerpt,built-in' => 'Excerpt',
    'post_date,built-in'    => 'Date',
    'post_status,built-in'  => 'Status',
    'post_author,built-in'  => 'Author',
    'post_name,built-in'    => 'Slug',
);

// fetch post type's ACF field groups for service general page
global $wpdb;
$schema_table_name = $wpdb->prefix . 'tcb_schema'; 

$service_area_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_area_posttype'
    )
);

$acf_fields = array();
$taxonomy_fields = array();
if($service_area_post_type){
    $field_groups = acf_get_field_groups(array('post_type' => $service_area_post_type));

    if($field_groups){
        foreach($field_groups as $group){
            $fields = acf_get_fields($group['ID']);
            if($fields){
                foreach($fields as $field){
                    $acf_fields[$field['name'] . ',ACF'] = $field['label'] . "(ACF)"; 
                }
            }
        }
    }

    $taxonomies = get_object_taxonomies($service_area_post_type, 'objects');

    if($taxonomies){
        foreach($taxonomies as $taxonomy){
            $taxonomy_fields[$taxonomy->name . ',taxonomy' ] = $taxonomy->label . "(Taxonomy)";
        }
    }
}else{
    $field_groups = acf_get_field_groups();

    foreach ($field_groups as $group) {
        // Get all fields for this group
        $fields = acf_get_fields($group['ID']);
        
        foreach ($fields as $field) {
            $acf_fields[$field['name'] . ',ACF'] = $field['label'] . "(ACF)"; 
        }
    }

    $taxonomies = get_taxonomies([], 'objects'); // get all taxonomies as objects

    foreach ($taxonomies as $taxonomy) {
        $taxonomy_fields[$taxonomy->name . ',taxonomy' ] = $taxonomy->label . "(Taxonomy)";
    }
}

$all_fields = array_merge($builtin_fields, $acf_fields,$taxonomy_fields);

?>
<div class="schema-generator-section" style="display:flex; gap:32px;">
    <div style="display:flex; flex-direction:column; gap:20px; width:50%;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Service Area</h2>
            <div style="display:flex; gap:16px;">
                <button id="schema-generator-service-area-save-btn" class="button button-primary">Save</button>
            </div>
        </div>

        <div>
            <label>Name</label><br>
            <select name="service-area-name" id="schema-generator-service-area-name">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Description</label><br>
            <select name="service-area-description" id="schema-generator-service-area-description">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Keywords</label><br>
            <select name="service-area-keywords" id="schema-generator-service-area-keywords">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label>Telephone</label><br>
            <select name="service-area-telephone" id="schema-generator-service-area-telephone">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Opening Hours</label>
            <div>
                <label>Monday</label><br>
                <select name="service-area-monday" id="schema-generator-service-area-monday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Tuesday</label><br>
                <select name="service-area-tuesday" id="schema-generator-service-area-tuesday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Wednesday</label><br>
                <select name="service-area-wednesday" id="schema-generator-service-area-wednesday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Thursday</label><br>
                <select name="service-area-thursday" id="schema-generator-service-area-thursday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Friday</label><br>
                <select name="service-area-friday" id="schema-generator-service-area-friday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Saturday</label><br>
                <select name="service-area-saturday" id="schema-generator-service-area-saturday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Sunday</label><br>
                <select name="service-area-sunday" id="schema-generator-service-area-sunday">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="schema-generator-service-area-address-section"  >
            <label>Address</label></br>
            <div>
                <input id="schema-generator-has-street-address-checkbox" name="hasStreetAddress" type="checkbox" checked/>
                <label>Has street address?</label>
            </div>
            <div id="schema-generator-service-area-street-address"><label>Street Address: </label></br>
            <select name="service-area-street-address">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></div>
            
            <label>City: </label></br>
            <select name="service-area-city" id="schema-generator-service-area-city">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select><br/>
            
            <label>Province: </label></br>
            <select name="service-area-province" id="schema-generator-service-area-province">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select><br/>
            
            <label>Postal code: </label></br>
            <select name="service-area-postal-code" id="schema-generator-service-area-postal-code">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select><br/>
            <div id="schema-generator-service-area-amenity-feature"><label>Amenity Feature: </label></br>
            <select name="service-area-amenity-feature">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>

        <div>
            <label>areaServed</label></br>
            <label>ID(Wikipedia Link): </label></br>
            <select name="service-area-street-areaserved-id" id="schema-generator-service-area-areaserved-id">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px;">
            <label>hasOfferCatalog</label>
            <div>
                <label>hasOfferCatalog name</label><br>
                <select name="capability-hasOfferCatalog-name" id="schema-generator-capability-hasOfferCatalog-name">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>hasOfferCatalog Description</label><br>
                <select name="capability-hasOfferCatalog-description" id="schema-generator-capability-hasOfferCatalog-description">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
               
    </div>  
    <!--Schema Generate Result-->
    <div style="width:50%; height:1000px;">
            <h4>Schema Example</h4>
            <textarea id="schema-generator-service-area-schema-result" style="display:block; width:100%; height:calc(100% - 18px); background:white; margin-top:20px; border-radius:20px; padding:16px;" readonly>
            
            </textarea>
    </div>     
</div>
    <style>
        .schema-generator-section input:not([type="checkbox"]){
            width:100%;
        }

        .schema-generator-section textarea{
            width:100%;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            //hideLoading();
            $.ajax({
                url: schemaAjax.ajax_url, // WordPress AJAX URL
                method: 'POST',
                data: {
                    action: 'service_area_generate_schema', // AJAX action name
                    nonce: schemaAjax.nonce // Security nonce
                },
                success: function(response) {
                    if(response.success){
                        //console.log(response.data)
                        done=0;
                        response.data.schema.forEach(element => {
                            console.log(JSON.parse(element));
                             if(done == 0 ){
                                $('#schema-generator-service-area-schema-result').val(JSON.stringify(JSON.parse(element),null,2));
                                done++;
                            }
                        });
                    }
                }
            });
            $.ajax({
                url: schemaAjax.ajax_url, // WordPress AJAX URL
                method: 'POST',
                data: {
                    action: 'get_schema_by_page', // AJAX action name
                    page: "service-area",                  // Page value to query
                    nonce: schemaAjax.nonce // Security nonce
                },
                success: function(response) {
                    if(response.success){
                        //console.log(response.data);
                        response.data.properties.forEach(element => {
                            $(`input[name=${element.property}]`).val(element.value);
                            $(`select[name=${element.property}]`).val(element.value);
                            $(`textarea[name=${element.property}]`).val(element.value);
                            if(element.value=='1'){
                                $(`input[name=${element.property}]`).prop('checked', true);
                            }
                            else if(element.value=='0'){
                                $(`input[name=${element.property}]`).prop('checked', false);
                            }
                        });
                        //generateSchema(response.data);
                        toggleAddressSection();
                        hideLoading();
                    } else {
                        console.error('Error:', response.data.message);
                        hideLoading();
                    }
                },
                error: function(err) {
                    console.error('AJAX error:', err);
                    hideLoading();
                }
            });

            //handle save button
            $('#schema-generator-service-area-save-btn').on('click', function(e) {
                e.preventDefault();

                let page = 'service-area';
                let data = [];


                // Collect all form fields
                $('input[name], select[name], textarea[name]').each(function() {
                    let property = $(this).attr('name');
                    let value;

                    if ($(this).attr('type') === 'checkbox') {
                        value = $(this).prop('checked') ? 1 : 0; // or true/false
                    } else {
                        if($(this).val() == ''){
                            value=null;
                        }else{
                            value = $(this).val();
                        }
                    }
                    data.push({
                        property: property,
                        value: value
                    });
                });
                
            // console.log(data);

                $.ajax({
                    url: schemaAjax.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'save_tcb_schema_bulk',
                        page: page,
                        data: data,
                        _ajax_nonce: schemaAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            //console.log(response.data);
                            alert('✅ Saved successfully!');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + response.data.message);
                        }
                    },
                    error: function(err) {
                        alert('AJAX error occurred');
                        console.error(err);
                    }
                });
            });

            // Function to toggle address section
            function toggleAddressSection() {
                if ($('#schema-generator-has-street-address-checkbox').is(':checked')) {
                    $('#schema-generator-service-area-street-address').show();
                    $('#schema-generator-service-area-amenity-feature').show();
                } else {
                    $('#schema-generator-service-area-street-address').hide();
                    $('#schema-generator-service-area-amenity-feature').hide();
                }
            }
            // Run whenever checkbox changes
            $('#schema-generator-has-street-address-checkbox').on('change', function() {
                    toggleAddressSection();
            });
            
        });
    </script>