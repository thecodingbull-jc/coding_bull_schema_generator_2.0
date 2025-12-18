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

$service_general_taxonomy_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_general_taxonomy'
    )
);

$service_general_taxonomy_term = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_general_term'
    )
);

$global_setting_address = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'single_location'
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

// fetch post type's ACF field groups for service capability page

$service_capability_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_capability_posttype'
    )
);

$service_capability_taxonomy_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_capability_taxonomy'
    )
);

$service_capability_taxonomy_term = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_capability_term'
    )
);

$service_capability_acf_fields = array();
if($service_capability_post_type){
    $service_capability_field_groups = acf_get_field_groups(array('post_type' => $service_capability_post_type));

    if($field_groups){
        foreach($field_groups as $group){
            $service_capability_fields = acf_get_fields($group['ID']);
            if($service_capability_fields){
                foreach($service_capability_fields as $field){
                    $service_capability_acf_fields[$field['name'] . ',ACF'] = $field['label'] . "(ACF)"; 
                }
            }
        }
    }

    $service_capability_taxonomies = get_object_taxonomies($service_capability_post_type, 'objects');

    $service_capability_taxonomy_fields = array();
    if($service_capability_taxonomies){
        foreach($service_capability_taxonomies as $taxonomy){
            $service_capability_taxonomy_fields[$taxonomy->name . ',taxonomy' ] = $taxonomy->label . "(Taxonomy)";
        }
    }
}

$service_capability_all_fields = array_merge($builtin_fields, $acf_fields,$taxonomy_fields);

// fetch post type's ACF field groups for service review page

$review_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'review_posttype'
    )
);

$review_acf_fields = array();
$review_field_groups = acf_get_field_groups(array('post_type' => $review_post_type));

// var_dump($review_field_groups);
if($review_field_groups){
    foreach($review_field_groups as $group){
        $review_fields = acf_get_fields($group['ID']);
        if($review_fields){
            foreach($review_fields as $field){
                $review_acf_fields[$field['name'] . ",ACF"] = $field['label'] . "(ACF)"; 
            }
        }
    }
}

//fetch number of reviews and aggregate rating
$review_rating_field = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'home_page',
        'aggregateRating-rating'
    )
);

$review_all_fields_selection = array_merge($builtin_fields, $review_acf_fields);

// Count published reviews
if($review_post_type){
    $review_count = (int) wp_count_posts($review_post_type)->publish;
    // Calculate average rating using ACF meta value
    $average_rating = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT AVG(CAST(meta_value AS DECIMAL(10,2))) 
            FROM $wpdb->postmeta 
            WHERE meta_key = %s 
            AND post_id IN (
                SELECT ID FROM $wpdb->posts 
                WHERE post_type = %s 
                AND post_status = 'publish'
            )
            ",
            explode(',',$review_rating_field)[0],
            $review_post_type
        )
    );
}else{
    $review_count = 0;
    $average_rating = 0;
}

//Employee selections

$employee_acf_fields = array();
$employee_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'employee_posttype'
    )
);

if($employee_post_type){
    $employee_field_groups = acf_get_field_groups(array('post_type' => $employee_post_type));

    if($employee_field_groups){
        foreach($employee_field_groups as $group){
            $employee_fields = acf_get_fields($group['ID']);
            if($employee_fields){
                foreach($employee_fields as $field){
                    $employee_acf_fields[$field['name'] . ',ACF'] = $field['label'] . "(ACF)"; 
                }
            }
        }
    }
}

$employee_all_fields = array_merge($builtin_fields, $employee_acf_fields);

// Optional: round it to one decimal place
$average_rating = round($average_rating, 1);

?>
<div class="schema-generator-section" style="display:flex; gap:32px;">
    <div style="display:flex; flex-direction:column; gap:20px; width:50%;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Local Business</h2>
            <div style="display:flex; gap:16px;">
                <button id="schema-generator-local-business-save-btn" class="button button-primary">Save</button>
            </div>
        </div>

        <div>
            <label>Name</label><br>
            <input value="<?php echo !empty($saved_settings['home_name']) ? $saved_settings['home_name'] : ''; ?>" type="text" id="schema-generator-home-name" name="name"/>
        </div>

        <div>
            <label>Description</label><br>
            <input value="<?php echo !empty($saved_settings['home_description']) ? $saved_settings['home_description'] : ''; ?>" type="text" id="schema-generator-home-description" name="description"/>
        </div>
        
        <div>
            <label for="businessType">Business Type:</label><br/>
            <select id="businessType" name="businessType">
                <option value="" disabled selected>Select business type</option>
                <option value="HomeAndConstructionBusiness">Home And Construction Business</option>
                <option value="MedicalBusiness">Medical Business</option>
            </select>
            <div>
                <label>Business Type: </label><br/><input name="businessType-text" type="text"/>
            </div>
        </div>

        <div>
            <label>Logo: </label><br/><input name="logo" type="text"/>
        </div>
        <div>
            <label>Keywords(Seperate by comma): </label><br/><textarea name="keywords" style="height:100px;"></textarea>
        </div>
        
        <div>
            <label>Telephones(Seperate by comma): </label><br/><textarea name="telephone" style="height:100px;"></textarea>
        </div>

         <div>
            <label>Social media(Seperate by comma): </label><br/><textarea name="social-media" style="height:100px;"></textarea>
        </div>
        
        

        <div style="display:<?echo $global_setting_address?"block":"none"?>;" >
            <label>Address</label></br>
            <div  style="display:<?echo $global_setting_address?"block":"none"?>;" >
                <input id="schema-generator-has-street-address-checkbox" name="hasStreetAddress" type="checkbox" checked/>
                <label>Has street address?</label>
            </div>
            <div id="schema-generator-street-address"><label>Street Address: </label></br><input name="streetAddress" id="streetAddress" type="text"/><br/></div>
            <label>City: </label></br><input name="addressLocality" id="addressLocality" type="text"/><br/>
            <label>Province/State: </label></br><input name="addressRegion" id="addressRegion" type="text"/><br/>
            <label>Postal Code: </label></br><input name="postalCode" id="postalCode" type="text"/>
            <div id="schema-generator-amenity-feature"><label>Amenity Feature:(Seperate by comma) </label></br><input name="amenityFeature" id="amenityFeature" type="text"/></div>
        </div>

        <div>
            <label>Price Rage: </label><br/><input name="priceRange" type="number" min="1" max="5"/>
        </div>

        <div>
            <label>Opening Hours</label></br>
            <label>MONDAY: </label></br><input name="monday" type="text"/><br/>
            <label>TUEDAY: </label></br><input name="tuesday" type="text"/><br/>
            <label>WEDDAY: </label></br><input name="wednesday" type="text"/><br/>
            <label>THUDAY: </label></br><input name="thursday" type="text"/><br/>
            <label>FRIDAY: </label></br><input name="friday" type="text"/><br/>
            <label>SATDAY: </label></br><input name="saturday" type="text"/><br/>
            <label>SUNDAY: </label></br><input name="sunday" type="text"/><br/>
        </div>

        <div style="display:<?echo $global_setting_address?"block":"none"?>;" >
            <label>Has Map</label><br/>
            <input name="hasMap" type="text"/>
        </div>

        <div>
            <label>Payments Accepted(Seperate by comma)</label><br/>
            <textarea name="paymentAccepted"></textarea>
        </div>

        <div>
            <label>Awards(Seperate by comma)</label><br/>
            <textarea name="awards"></textarea>
        </div>

        <div>
            <label>Knows Language(Seperate by comma, format: "name|alternate name")</label><br/>
            <textarea name="knowsLanguage"></textarea>
        </div>

        <!-- <div style="display:flex; flex-direction:column; gap:8px;">
            <label>areaServed</label>
            <div>
                <label>City</label>
                <select name="areaServed-city" id="schema-generator-areaServed-ciity">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>addressRegion</label>
                <select name="areaServed-province" id="schema-generator-areaServed-province">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>sameAs</label>
                <select name="areaServed-id" id="schema-generator-areaServed-id">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div> -->

        <!-- <div style="display:<?echo $global_setting_address?"none":"flex"?>; flex-direction:column; gap:8px;"  style="display:<?echo $global_setting_address?"block":"none"?>;" >
            <label>Address</label>
            <div>
                <label>Street Address</label>
                <select name="address-street">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>City</label>
                <select name="address-city">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Province</label>
                <select name="address-province">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Postal code</label>
                <select name="address-postal">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div> -->

        <div style="display:flex; flex-direction:column; gap:8px;">
            <label>hasOfferCatalog(General)</label>
            <div>
                <label>hasOfferCatalog name</label>
                <select name="hasOfferCatalog-name" id="schema-generator-hasOfferCatalog-name">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>hasOfferCatalog Service Area</label>
                <select name="hasOfferCatalog-service-area" id="schema-generator-hasOfferCatalog-service-area">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>hasOfferCatalog Description</label>
                <select name="hasOfferCatalog-description" id="schema-generator-hasOfferCatalog-description">
                    <option value="" selected>Select field name</option>
                    <?php foreach($all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px;">
            <label>hasOfferCatalog(Capability)</label>
            <div>
                <label>hasOfferCatalog name</label>
                <select name="capability-hasOfferCatalog-name" id="schema-generator-capability-hasOfferCatalog-name">
                    <option value="" selected>Select field name</option>
                    <?php foreach($service_capability_all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>hasOfferCatalog Service Area</label>
                <select name="capability-hasOfferCatalog-service-area" id="schema-generator-capability-hasOfferCatalog-service-area">
                    <option value="" selected>Select field name</option>
                    <?php foreach($service_capability_all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>hasOfferCatalog Description</label>
                <select name="capability-hasOfferCatalog-description" id="schema-generator-capability-hasOfferCatalog-description">
                    <option value="" selected>Select field name</option>
                    <?php foreach($service_capability_all_fields as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
            
        <div id="schema-generator-medical-business-section" style="display:flex; flex-direction:column; gap:20px;">
            <div>
                <h4>Medical Business Schema</h4>
                <input name="isAcceptingNewPatients" type="checkbox"/>
                <label>Accepting New Patients</label><br/>
            </div>

            <div>
                <label for="medicalSpecialty">Medical Specialty:</label><br/>
                <select id="medicalSpecialty" name="medicalSpecialty">
                    <option value="" disabled selected>Select Medical Specialty</option>
                    <option value="Hospital">Hospital</option>
                    <option value="MedicalClinic">Medical Clinic</option>
                    <option value="MedicalOrganization">Medical Organization</option>
                    <option value="Physician">Physician</option>  
                </select>
                <!--Missing certification and credential-->
                <div>
                    <label>hasCredential(format: "competencyRequired|credentialCategory|recognizedBy", seperate by comma)</label>
                    <input name="medical-business-credential"/>
                </div>

                <div>
                    <label>hasCertification(format: "'certificationIdentification|issuedBy", seperate by comma)</label>
                    <input name="medical-business-certification"/>
                </div>

                
            </div>
        </div>  

    </div>  

    <!--Schema Generate Result-->
    <div style="width:50%;">
        <h4>Schema</h4>
        <textarea id="schema-generator-schema-result" style="display:block; width:100%; height:calc(100% - 18px); background:white; margin-top:20px; border-radius:20px; padding:16px;" readonly>
        
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
        //generate schema
        $.ajax({
            url: schemaAjax.ajax_url, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'homepage_generate_schema', // AJAX action name
                nonce: schemaAjax.nonce // Security nonce
            },
            success: function(response) {
                if(response.success){
                    //$('#schema-generator-service-area-schema-result').val(JSON.stringify(JSON.parse(response.data.schema),null,2));
                    console.log(response.data)
                    const obj = response.data.schema; 

                    const prettyJson = JSON.stringify(obj, null, 4); 

                    $('#schema-generator-schema-result').val(prettyJson);
                }
            }
        });

        //fetch schema
        $.ajax({
            url: schemaAjax.ajax_url, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'get_schema_by_page', // AJAX action name
                page: "home_page",                  // Page value to query
                nonce: schemaAjax.nonce // Security nonce
            },
            success: function(response) {
                if(response.success){
                    console.log(response.data);
                    response.data.properties.forEach(element => {
                        $(`input[name=${element.property}]`).val(element.value);
                        $(`select[name=${element.property}]`).val(element.value);
                        $(`textarea[name=${element.property}]`).val(element.value);
                        if(element.value=='1'){
                            $(`input[name=${element.property}]`).prop('checked', true);
                            toggleMedicalBusinessSection();
                            <?if(isset($global_setting_address) && $global_setting_address){?>
                                toggleAddressSection();
                            <?}?>
                        }
                        else if(element.value=='0'){
                            $(`input[name=${element.property}]`).prop('checked', false);
                            <?if(isset($global_setting_address) && $global_setting_address){?>
                                toggleAddressSection();
                            <?}?>
                        }else{
                            toggleMedicalBusinessSection();
                        }
                    });
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

        //handle business type change
        // Function to toggle medical business section
        function toggleMedicalBusinessSection() {
            if ($('#businessType').val() === 'MedicalBusiness') {
                $('#schema-generator-medical-business-section').show();
            } else {
                $('#schema-generator-medical-business-section').hide();
            }
        }

        // Run whenever businessType changes
        $('#businessType').on('change', function() {
            toggleMedicalBusinessSection();
        });

        // Function to toggle address section
        function toggleAddressSection() {
            if ($('#schema-generator-has-street-address-checkbox').is(':checked')) {
                $('#schema-generator-street-address').show();
                $('#schema-generator-amenity-feature').show();
            } else {
                $('#schema-generator-street-address').hide();
                $('#schema-generator-amenity-feature').hide();
            }
        }

        // Run whenever checkbox changes
        $('#schema-generator-has-street-address-checkbox').on('change', function() {
            //if(<?echo $global_setting_address?>){
                toggleAddressSection();
            //}
        });

        //handle save button
        $('#schema-generator-local-business-save-btn').on('click', function(e) {
            e.preventDefault();

            let page = 'home_page';
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