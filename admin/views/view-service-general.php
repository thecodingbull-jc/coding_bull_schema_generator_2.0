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

$service_general_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_general_posttype'
    )
);

$acf_fields = array();
$taxonomy_fields = array();
if($service_general_post_type){
    $field_groups = acf_get_field_groups(array('post_type' => $service_general_post_type));

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

    $taxonomies = get_object_taxonomies($service_general_post_type, 'objects');

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
<div class="schema-generator-section" style="display:flex; gap:32px; height:1000px;">
    <div style="display:flex; flex-direction:column; gap:20px; width:50%;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Service General Pages</h2>
            <div style="display:flex; gap:16px;">
                <button id="schema-generator-service-general-save-btn" class="button button-primary">Save</button>
            </div>
        </div>

        <div>
            <label>Name</label><br>
            <select name="service-general-name" id="schema-generator-service-general-name">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Description</label><br>
            <select name="service-general-description" id="schema-generator-service-general-description">
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Keywords</label><br>
            <select name="service-general-keywords" id="schema-generator-service-general-keywords">
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
    <div style="width:50%;">
            <h4>Schema Example</h4>
            <textarea id="schema-generator-service-general-schema-result" style="display:block; width:100%; height:calc(100% - 18px); background:white; margin-top:20px; border-radius:20px; padding:16px;" readonly>
            
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
                    action: 'service_general_generate_schema', // AJAX action name
                    nonce: schemaAjax.nonce // Security nonce
                },
                success: function(response) {
                    if(response.success){
                        done = 0;
                        response.data.schema.forEach(element => {
                            console.log(JSON.parse(element));
                            if(done == 0 ){
                                $('#schema-generator-service-general-schema-result').val(JSON.stringify(JSON.parse(element),null,2));
                                done++;
                            }
                            
                        });
                        console.log(response.data)
                    }
                }
            });
            $.ajax({
                url: schemaAjax.ajax_url, // WordPress AJAX URL
                method: 'POST',
                data: {
                    action: 'get_schema_by_page', // AJAX action name
                    page: "service-general",                  // Page value to query
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
            $('#schema-generator-service-general-save-btn').on('click', function(e) {
                e.preventDefault();

                let page = 'service-general';
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
        });
    </script>