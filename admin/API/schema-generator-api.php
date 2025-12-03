<?php

//Handle AJAX requet to fetch schema
add_action('wp_ajax_get_schema_by_page', 'get_schema_by_page');

function get_schema_by_page() {
    // Security check using nonce
    check_ajax_referer('schema_nonce', 'nonce');

    // Check if 'page' parameter is provided
    if ( empty($_POST['page']) ) {
        wp_send_json_error(['message' => 'Page is required']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tcb_schema';
    $page = sanitize_text_field($_POST['page']);

    // Get all rows for the specified page
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table WHERE page = %s", $page),
        ARRAY_A
    );

    //Count employee number
    $employee_post_type = $wpdb->get_var(
        $wpdb->prepare("SELECT value FROM $table WHERE page = 'global' AND property = 'employee_posttype'")
    );
    $count_employee = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish'",
        $employee_post_type
    )
);

    // Return results as JSON
    $response = [
        'properties' => $results
    ];
    if($count_employee >0){
        $response['numberOfEmployee'] = $count_employee;
    }
    wp_send_json_success($response);
}

// Handle AJAX request to save multiple schema properties at once
add_action('wp_ajax_save_tcb_schema_bulk', 'save_tcb_schema_bulk');

function save_tcb_schema_bulk() {
    check_ajax_referer('schema_nonce'); // Security check

    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';

    $page = sanitize_text_field($_POST['page'] ?? '');
    $data = $_POST['data'] ?? []; // Expecting an array of {property, value}

    if (empty($page) || empty($data) || !is_array($data)) {
        wp_send_json_error(['message' => 'Invalid input data']);
    }

    //update to database

    foreach ($data as $item) {
        $property = sanitize_text_field($item['property'] ?? '');
        $value    = sanitize_text_field($item['value'] ?? '');
        if($page == "home_page" && $property=="hasStreetAddress" && !$value ){
            
            $properties = ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode'];

            $placeholders = implode(',', array_fill(0, count($properties), '%s'));

            $sql = $wpdb->prepare(
                "DELETE FROM $table_name WHERE page = %s AND property IN ($placeholders)",
                array_merge(['home_page'], $properties)
            );

            $wpdb->query($sql);
        }
        if (empty($property)) continue;
        // Check if property exists
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table_name WHERE property = %s AND page = %s", $property, $page)
        );
        if ($value == '' && $existing) {
            $wpdb->delete(
                $table_name,
                ['id' => $existing],
                ['%d']
            );
        }
        if ($value=='') continue;
        if ($existing) {
            $wpdb->update(
                $table_name,
                ['value' => $value],
                ['id' => $existing],
                ['%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'property' => $property,
                    'value' => $value,
                    'page' => $page,
                ],
                ['%s', '%s', '%s']
            );
        }
    }

    wp_send_json_success(['message' => 'All fields saved successfully']);
}


// fetch all terms of a taxonomy
add_action('wp_ajax_get_terms_by_taxonomy', function(){
    $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? '');
    if(!$taxonomy){
        wp_send_json_error('No taxonomy provided');
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);

    $data = [];
    if(!is_wp_error($terms)){
        foreach($terms as $term){
            $data[$term->term_id] = $term->name;
        }
    }

    wp_send_json_success($data);
});

// Save global settings to database
add_action('wp_ajax_save_schema_global_settings', function(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';
    $settings = $_POST['settings'] ?? [];

    if(!is_array($settings)){
        wp_send_json_error('Invalid settings');
    }

    foreach($settings as $property => $value){
        $property = sanitize_text_field($property);
        $value = sanitize_text_field($value);
        $page = 'global'; // Identify settings page

        // If value is empty → delete the record
        if($value === ''){
            $wpdb->delete($table_name, ['property' => $property, 'page' => $page]);
        } else {
            // Check if property exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE property = %s AND page = %s",
                $property, $page
            ));

            if($exists){
                // Update
                $wpdb->update(
                    $table_name,
                    ['value' => $value],
                    ['property' => $property, 'page' => $page]
                );
            } else {
                // Insert
                $wpdb->insert(
                    $table_name,
                    ['property' => $property, 'value' => $value, 'page' => $page]
                );
            }
        }
    }

    wp_send_json_success('Settings saved');
});

//generate schema for home page
add_action('wp_ajax_homepage_generate_schema', 'homepage_generate_schema');

function homepage_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';
    //get homepage properties
    $homepage_properties = [];
    $schema=[];
    $homepage_properties_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'home_page'
        )
    );
    foreach ($homepage_properties_rows as $row){
        $homepage_properties[ $row->property ] = $row->value;
    }

    //service area taxo
    $service_area_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s AND property = %s",
            'global',
            'service_area_taxonomy_slug'
        )
    );

    //single address from global
    $single_address = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s AND property = %s",
            'global',
            'single_location'
        )
    );

    $schema['@context']='https://schema.org';
    //type
    if($homepage_properties['businessType']){
        $schema['@type']=$homepage_properties['businessType'];
    }
    //name
    if($homepage_properties['name']){
        $schema['name']=$homepage_properties['name'];
    }
    //description
    if($homepage_properties['description']){
        $schema['description']=$homepage_properties['description'];
    }
    //logo
    if($homepage_properties['logo']){
        $schema['logo']=$homepage_properties['logo'];
    }
    //keywords
    if($homepage_properties['keywords']){
        $schema['keywords']=$homepage_properties['keywords'];
    }
    //telephone
    if($homepage_properties['keywords']){
        $schema['telephone']=explode(',',$homepage_properties['telephone']);
    }
    //address
    if($single_address && ($homepage_properties['addressLocality'] || $homepage_properties['addressRegion'] || $homepage_properties['postalCode'] || $homepage_properties['streetAddress'] )){
        $address_schema = [];
        $address_schema['addressLocality'] = $homepage_properties['addressLocality'];
        $address_schema['addressRegion'] = $homepage_properties['addressRegion'];
        $address_schema['postalCode'] = $homepage_properties['postalCode'];
        if($homepage_properties['hasStreetAddress']){
            $address_schema['streetAddress'] = $homepage_properties['streetAddress'];
            $amanity_features= explode(',',$homepage_properties['amenityFeature']);
            $amanity_schema =[];
            foreach($amanity_features as $feature){
                $amanity_schema[] = ["@type"=>"LocationFeatureSpecification", "name"=>$feature];
            }
            $schema['amenityFeature'] = $amanity_schema;
        }
        
        $schema['address'] = $address_schema;
    }
    //priceRange
    if($homepage_properties['priceRange']){
        $schema["priceRange"] = str_repeat('$', intval($homepage_properties['priceRange']));
    }
    //openingHours
    $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    $hours_schema = [];
    if($homepage_properties["monday"]||$homepage_properties["tuesday"]||$homepage_properties["wednesday"]||$homepage_properties["thursday"]||$homepage_properties["friday"]||$homepage_properties["saturday"]||$homepage_properties["sunday"]){
        foreach($days as $day){
            if($homepage_properties[$day]){
                $hours_schema[] = substr($day,0,2) . ' ' . $homepage_properties[$day];
            }
        }
        $schema['openingHours'] = $hours_schema;
    }
    //hasMap
    if($homepage_properties['hasMap'] && $single_address){
        $schema['hasMap']=$homepage_properties['hasMap'];
    }
    //paymentAccepted
    if($homepage_properties['paymentAccepted']){
        $schema['paymentAccepted']=$homepage_properties['paymentAccepted'];
    }
    //awards
    if($homepage_properties['awards']){
        $schema['award']=explode(',',$homepage_properties['awards']);
    }
    //knowsLanguage
    if($homepage_properties['knowsLanguage']){
        $knowsLanguage = explode(',',$homepage_properties['knowsLanguage']);
        $knowsLanguage_schema = [];
        foreach ($knowsLanguage as $language) {
            $single_knowsLanguage_schema = [];
            $single_knowsLanguage_schema["name"] = explode('|',$language)[0];
            $single_knowsLanguage_schema["alternateName"] = explode('|',$language)[1];
            $knowsLanguage_schema[] = $single_knowsLanguage_schema;
        }
        $schema["knowsLanguage"] = $knowsLanguage_schema;
    }
    //employee
    $employee_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'employee_posttype'
        )
    );

    $employee_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property,  value FROM $table_name WHERE page = %s",
            'employee'
        ),ARRAY_A
    );
    $employee_settings = [];
    if ( ! empty( $employee_rows ) ) {
        foreach ( $employee_rows as $row ) {
            $employee_settings[ $row['property'] ] = $row['value'];
        }
    }
    if(isset($employee_post_type)){
        $employee_args = [
            'post_type'      => $employee_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $employee_query = new WP_Query($employee_args);
        $employee_result = generate_employee_schema($employee_post_type,$employee_settings,$employee_query);
        $schema['numberOfEmployees'] = $employee_query->found_posts;
        $schema['employee'] = $employee_result;
    }
    
    //hasCatalog(General & Capability)
    $areaServed_schema = [];
    $service_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_general_posttype'
        )
    );
    $general_service_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_general_taxonomy'
        )
    );
    $general_service_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_general_term'
        )
    );
    $capability_service_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_taxonomy'
        )
    );
    $capability_service_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_term'
        )
    );
    $manual_service_general_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_general_posts'
        )
    );
    $manual_service_capability_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_capability_posts'
        )
    );
    if(isset($manual_service_general_posts)){
        $manual_service_general_posts = json_decode(stripslashes($manual_service_general_posts),true);
    }else{
        $manual_service_general_posts = [];
    }
    if(isset($manual_service_capability_posts)){
        $manual_service_capability_posts = json_decode(stripslashes($manual_service_capability_posts),true);
    }else{
        $manual_service_capability_posts = [];
    }
    $service_result = [];
    $service_args = [];
    if(isset($service_post_type)){
        $service_args = [
            'post_type'      => $service_post_type,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];

        $service_args['tax_query'] = [
            'relation' => 'OR',
            [
                'taxonomy' => $general_service_taxo,
                'field'    => 'id',
                'terms'    => $general_service_term
            ],
            [
                'taxonomy' => $capability_service_taxo,
                'field'    => 'id',
                'terms'    => $capability_service_term
            ],
        ];
    }
    elseif((isset($manual_service_general_posts) && $manual_service_general_posts!=[]) || (isset($manual_service_capability_posts) && $manual_service_capability_posts!=[])){
        $manual_service_merge =  array_merge($manual_service_general_posts,$manual_service_capability_posts);
        $service_args = [
            'post_type' => 'any',
            'post__in'       => $manual_service_merge,
            'orderby'        => 'post__in',
            'posts_per_page' => -1
        ];
    }
    else{
        $service_args = [];
    }

    $service_query = new WP_Query($service_args);
    $areaServed = [];
    $areaServedSchema = [];
    if ($service_query->have_posts()) {
        while ($service_query->have_posts()) {
            $service_query->the_post();
            $single_service = [];
            $single_areaServed = [];
            $single_areaServed['@type'] = "City" ;
            if($homepage_properties['hasOfferCatalog-name']){
                $field = explode(',', $homepage_properties['hasOfferCatalog-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_service['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_service['name'] = get_field($field_name);
                }
            }
            if($homepage_properties['hasOfferCatalog-description']){
                $field = explode(',', $homepage_properties['hasOfferCatalog-description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_service['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_service['description'] = get_field($field_name);
                }
            }
            if(!$single_location){
                $city_field = explode(',', $homepage_properties['areaServed-city']);
                $city_field_name = $city_field[0];
                $city_field_type = $city_field[1];
                $province_field = explode(',', $homepage_properties['areaServed-province']);
                $province_field_name = $province_field[0];
                $province_field_type = $province_field[1];
                $id_field = explode(',', $homepage_properties['areaServed-id']);
                $id_field_name = $id_field[0];
                $id_field_type = $id_field[1];

                if ($city_field_type == 'built-in') {
                    if( !in_array(get_post_field($city_field_name), $areaServed)){
                        $single_areaServed['name'] = get_post_field($city_field_name);
                        $areaServed[] =get_post_field($city_field_name);
                        if ($province_field_type == 'built-in') {
                            $single_areaServed['addressRegion'] = get_post_field($province_field_name)??"";
                        } elseif ($province_field_type == 'ACF') {
                            $single_areaServed['addressRegion'] = get_field($province_field_name)??"";
                        }
                        if ($id_field_type == 'built-in') {
                            $single_areaServed['sameAs'] = get_post_field($id_field_name)??"";
                        } elseif ($id_field_type == 'ACF') {
                            $single_areaServed['sameAs'] = get_field($id_field_name)??"";
                        }
                    }
                } elseif ($city_field_type == 'ACF') {
                    if( !in_array(get_field($city_field_name), $areaServed)){
                        $single_areaServed['name'] = get_field($city_field_name);
                        $areaServed[] = get_field($city_field_name);
                        if ($province_field_type == 'built-in') {
                            $single_areaServed['addressRegion'] = get_post_field($province_field_name)??"";
                        } elseif ($province_field_type == 'ACF') {
                            $single_areaServed['addressRegion'] = get_field($province_field_name)??"";
                        }
                        if ($id_field_type == 'built-in') {
                            $single_areaServed['sameAs'] = get_post_field($id_field_name)??"";
                        } elseif ($id_field_type == 'ACF') {
                            $single_areaServed['sameAs'] = get_field($id_field_name)??"";
                        }
                    }
                }
            }
            $single_service['URL'] = get_post_permalink(get_the_ID());
            $service_result[] = $single_service;
            if($single_areaServed !== ["@type"=>"City"]){
                $areaServedSchema[] = $single_areaServed;
            }
            
        }
        if($areaServedSchema != [] && !$single_address){
            $schema["areaServed"] = $areaServedSchema;
        }
        $schema["hasOfferCatalog"] = $service_result;
    }
    wp_reset_postdata();

    //Reviews
    $aggregateRating_schema = [];
    $aggregateRating_schema['@type'] = "AggregateRating";
    $total_rating = 0;
    $review_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'review_posttype'
        )
    );
    $review_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property,  value FROM $table_name WHERE page = %s",
            'review'
        ),ARRAY_A
    );
    $review_settings = [];
    if ( ! empty( $review_rows ) ) {
        foreach ( $review_rows as $row ) {
            $review_settings[ $row['property'] ] = $row['value'];
        }
    }
    
    if(isset($review_post_type)){
        $review_args = [
            'post_type'      => $review_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        $review_query = new WP_Query($review_args);
        $total_reviews = $review_query->post_count;
        $review_result = generate_review_schema($review_post_type,$review_settings,$review_query);
        if ($review_query->have_posts()) {
            while ($review_query->have_posts()) {
                $review_query->the_post();
                 if($review_settings['review-rating']){
                    $field = explode(',', $review_settings['review-rating']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $total_rating += intval(get_post_field($field_name));
                    } elseif ($field_type == 'ACF') {
                        $total_rating += intval(get_field($field_name));
                    }
                    $single_review["reviewRating"] = $reviewRating;
                }
            }
        }
        $aggregateRating_schema['ratingValue'] = $total_rating/$total_reviews;
        $aggregateRating_schema['reviewCount'] = $total_reviews;
        $schema['aggregateRating'] = $aggregateRating_schema;
        $schema['review'] = $review_result;
    }

    //Medical business
     if($homepage_properties['businessType'] == "MedicalBusiness"){
        if($homepage_properties['isAcceptingNewPatients']){
            $schema['isAcceptingNewPatients']=true;
        }else{
            $schema['isAcceptingNewPatients']=false;
        }
        if($homepage_properties['medicalSpecialty']){
            $schema['medicalSpecialty']=$homepage_properties['medicalSpecialty'];
        }
        if($homepage_properties['medical-business-credential']){
            $hasCredential_schema = [];
            $credentials = explode(',',$homepage_properties['medical-business-credential']);
            foreach ($credentials as $credential){
                $hasCredential_schema[] = [
                    'competencyRequired'=>explode("|",$credential)[0],
                    'credentialCategory'=>explode("|",$credential)[1],
                    'recognizedBy'=>[
                        "@type"=> "Organization",
                        "name"=> explode("|",$credential)[2]
                    ]
                ];
            }
            $schema['hasCredential']=$hasCredential_schema;
        }
        if($homepage_properties['medical-business-certification']){
            $hasCertification_schema = [];
            $certifications = explode(',',$homepage_properties['medical-business-certification']);
            foreach ($certifications as $certification){
                $hasCertification_schema[] = [
                    'certificationIdentification'=>explode("|",$credential)[0],
                    'issuedBy'=>[
                        "@type"=> "Organization",
                        "name"=> explode("|",$certification)[1]
                    ]
                ];
            }
            $schema['hasCertification']=$hasCertification_schema;
        }
    }
    update_option('homepage_jsonld_script', json_encode($schema));

    //wp_reset_postdata();
    wp_send_json_success([
        //'properties' => $homepage_properties,
        'schema' => $schema,
        'testing'=> $areaServed
    ]);
}

//generate schema for service area pages
add_action('wp_ajax_service_area_generate_schema', 'service_area_generate_schema');

function service_area_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';
    $post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_posttype'
        )
    );
    $post_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy'
        )
    );
    $post_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_term'
        )
    );
    $service_area_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy_slug'
        )
    );

    $manual_service_area_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_area_posts'
        )
    );
    if(isset($manual_service_area_posts)){
        $manual_service_area_posts = json_decode(stripslashes($manual_service_area_posts),true);
    }else{
        $manual_service_area_posts = [];
    }

    $manual_service_general_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_general_posts'
        )
    );
    if(isset($manual_service_general_posts)){
        $manual_service_general_posts = json_decode(stripslashes($manual_service_general_posts),true);
    }else{
        $manual_service_general_posts = [];
    }

    //fetch all posts
    if($post_type!=""){
        $post_args = [
            'post_type'=> $post_type,
            'posts_per_page' => -1,
            'status' => 'publish'
        ];

        if ($post_taxo && $post_term){
            $post_args["tax_query"] =[ [
                'taxonomy' => $post_taxo,  
                'field'    => 'id',
                'terms'    => $post_term
            ]];
        }
    }elseif(isset($manual_service_area_posts) && $manual_service_area_posts!=[]){
        $post_args = [
            'post_type' => 'any',
            'post__in'       => $manual_service_area_posts,
            'orderby'        => 'post__in',
            'posts_per_page' => -1
        ];
    }else{
        $post_args = [];
    }

    $posts_query = new WP_Query($post_args);

    $results = [];
    $check = [];

    //Properties from home page
    $home_logo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'logo'
        )
    );
    $home_businessType = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'businessType'
        )
    );
    $home_priceRange = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'priceRange'
        )
    );
    $home_paymentAccepted = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'paymentAccepted'
        )
    );
    $home_award = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'awards'
        )
    );
    $home_knowsLanguage = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'knowsLanguage'
        )
    );
    $home_telephone = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'telephone'
        )
    );
    $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    $home_hours = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value 
            FROM $table_name 
            WHERE property IN (" . implode(',', array_fill(0, count($days), '%s')) . ")",
            ...$days
        )
    );

    //fetch schema setting
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'service-area'
        ),
        ARRAY_A
    );

    $saved_settings = [];
    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            $saved_settings[ $row['property'] ] = $row['value'];
        }
    }

    //loop through posts and generate schema for each post
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();  
            $post_id = get_the_ID();
            $schema = [];
            $schema["@context"] = "https://schema.org";
            //home_page
            if($home_businessType){
                $schema["@type"] = $home_businessType;
            }
            if($home_logo){
                $schema["logo"] = $home_logo;
            }
            if($home_priceRange){
                $schema["priceRange"] = str_repeat('$', intval($home_priceRange));
            }
            if($paymentAccepted){
                $schema["paymentAccepted"] = $paymentAccepted;
            }
            if($home_award){
                $schema["award"] = explode(',', $home_award);
            }
            if($home_knowsLanguage){
                $knowsLanguage = explode(',',$home_knowsLanguage);
                $knowsLanguage_schema = [];
                foreach ($knowsLanguage as $language) {
                    $single_knowsLanguage_schema = [];
                    $single_knowsLanguage_schema["name"] = explode('|',$language)[0];
                    $single_knowsLanguage_schema["alternateName"] = explode('|',$language)[1];
                    $knowsLanguage_schema[] = $single_knowsLanguage_schema;
                }
                $schema["knowsLanguage"] = $knowsLanguage_schema;
            }
            if($saved_settings['service-area-telephone']){
                $field = explode(',', $saved_settings['service-area-telephone']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['telephone'] = explode(',',get_post_field($field_name));
                } elseif ($field_type == 'ACF') {
                    $schema['telephone'] = explode(',',get_field($field_name));
                }
            }else{
                if($home_telephone){
                    $schema["telephone"] = explode(',', $home_telephone);
                }
            }
            if($saved_settings['service-area-monday']||$saved_settings['service-area-tuesday']||$saved_settings['service-area-wednesday']||$saved_settings['service-area-thursday']||$saved_settings['service-area-friday']||$saved_settings['service-area-saturday']||$saved_settings['service-area-sunday']){
                $hours_schema = [];
                if($saved_settings['service-area-monday']){
                    $field = explode(',', $saved_settings['service-area-monday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'mo ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'mo ' . get_field($field_name);
                    }
                }
                if($saved_settings['service-area-tuesday']){
                    $field = explode(',', $saved_settings['service-area-tuesday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'tu ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'tu ' . get_field($field_name);
                    }
                }
                if($saved_settings['service-area-wednesday']){
                    $field = explode(',', $saved_settings['service-area-wednesday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'we ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'we ' . get_field($field_name);
                    }
                }
                if($saved_settings['service-area-thursday']){
                    $field = explode(',', $saved_settings['service-area-thursday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'th ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'th ' . get_field($field_name);
                    }
                }
                if($saved_settings['service-area-friday']){
                    $field = explode(',', $saved_settings['service-area-friday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'fr ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'fr ' . get_field($field_name);
                    }
                }
                if($saved_settings['service-area-saturday']){
                    $field = explode(',', $saved_settings['service-area-saturday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'sa ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'sa ' . get_field($field_name);
                    }
                }
                if($saved_settings['service-area-sunday']){
                    $field = explode(',', $saved_settings['service-area-sunday']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $hours_schema[] = 'su ' . get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $hours_schema[] = 'su ' . get_field($field_name);
                    }
                }
                $schema['openingHours'] = $hours_schema;
            }
            else{
                 if($home_hours){
                    $formated_hours = [];
                    if ( $home_hours ) {
                        foreach ( $home_hours as $row ) {
                            $formated_hours[] = substr($row->property, 0, 2) . ' ' . $row->value;
                        }
                    }
                    $schema["openingHours"] = $formated_hours;
                }
            }
           
            //name
            if($saved_settings['service-area-name']){
                $field = explode(',', $saved_settings['service-area-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['name'] = get_field($field_name);
                }
            }
            //description
            if($saved_settings['service-area-description']){
                $field = explode(',', $saved_settings['service-area-description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['description'] = get_field($field_name);
                }
            }
            //keywords
            if($saved_settings['service-area-keywords']){
                $field = explode(',', $saved_settings['service-area-keywords']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['keywords'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['keywords'] = get_field($field_name);
                }
            }
            //address
            $address=[];
            if($saved_settings['hasStreetAddress']){
                if($saved_settings['service-area-street-address']){
                    $field = explode(',', $saved_settings['service-area-street-address']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['streetAddress'] = get_post_field($field_name);
                    } elseif ($field_type == 'ACF') {
                        $address['streetAddress'] = get_field($field_name);
                    }
                }
            }
            
            if($saved_settings['service-area-city']){
                $field = explode(',', $saved_settings['service-area-city']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $address['addressLocality'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $address['addressLocality'] = get_field($field_name);
                }
            }
            if($saved_settings['service-area-province']){
                $field = explode(',', $saved_settings['service-area-province']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $address['addressRegion'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $address['addressRegion'] = get_field($field_name);
                }
            }
            if($saved_settings['service-area-postal-code']){
                $field = explode(',', $saved_settings['service-area-postal-code']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $address['postalCode'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $address['postalCode'] = get_field($field_name);
                }
            }

            $schema['address'] = $address;
            //amenity feature
            if($saved_settings['hasStreetAddress']){
                if($saved_settings['service-area-amenity-feature']){
                    $field = explode(',', $saved_settings['service-area-amenity-feature']);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $amenity_features=explode(',', get_post_field($field_name));
                        $amenity_schema = [];
                        foreach($amenity_features as $feature){
                            $amenity_schema[] = ["@type"=>"LocationFeatureSpecification","name"=>$feature];
                        }
                        $schema['amenityFeature'] = $amenity_schema;
                    } elseif ($field_type == 'ACF') {
                        $amenity_features=explode(',',get_field($field_name)) ;
                        $amenity_schema = [];
                        foreach($amenity_features as $feature){
                            $amenity_schema[] = ["@type"=>"LocationFeatureSpecification","name"=>$feature];
                        }
                        $schema['amenityFeature'] = $amenity_schema;
                    }
                }
            }
            //areaServed
            $areaServed=[];
            if($saved_settings['service-area-city']){
                $field = explode(',', $saved_settings['service-area-city']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $areaServed['addressLocality'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $areaServed['addressLocality'] = get_field($field_name);
                }
            }
            if($saved_settings['service-area-street-areaserved-id']){
                $field = explode(',', $saved_settings['service-area-street-areaserved-id']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $areaServed['id'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $areaServed['id'] = get_field($field_name);
                }
            }
            $schema['areaServed'] = $areaServed;
            
            //employee
            $employee_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'employee_posttype'
                )
            );
            $employee_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT property,  value FROM $table_name WHERE page = %s",
                    'employee'
                ),ARRAY_A
            );
            $employee_settings = [];
            if ( ! empty( $employee_rows ) ) {
                foreach ( $employee_rows as $row ) {
                    $employee_settings[ $row['property'] ] = $row['value'];
                }
            }
            $terms = get_the_terms($post_id, $service_area_slug);
            if(isset($employee_post_type)){
                $employee_args = [
                    'post_type'      => $employee_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'tax_query'      => [
                        'relation' => 'OR', // OR relation between multiple terms
                    ],
                ];
                foreach ($terms as $term_slug) {
                    $employee_args['tax_query'][] = [
                        'taxonomy' => $service_area_slug,
                        'field'    => 'slug',
                        'terms'    => $term_slug,
                    ];
                }
                $employee_query = new WP_Query($employee_args);
                $employee_result = generate_employee_schema($employee_post_type,$employee_settings,$employee_query);
                $schema['employee'] = $employee_result;
            }
           
            //hasOfferCatelog
            $service_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'service_general_posttype'
                )
            );
            $terms = get_the_terms($post_id, $service_area_slug);
            $service_result = [];
            if(isset($service_post_type)){
                $service_args = [
                    'post_type'      => $service_post_type,
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'tax_query'      => [
                        'relation' => 'OR', // OR relation between multiple terms
                    ],
                ];

                // Loop through terms and add each to tax_query
                foreach ($terms as $term) {
                    $service_args['tax_query'][] = [
                        'taxonomy' => $service_area_slug,
                        'field'    => 'slug',
                        'terms'    => $term->slug,
                    ];
                }
            }elseif(isset($manual_service_general_posts) && $manual_service_general_posts!=[]){
                $service_args = [
                    'post__in'       => $manual_service_general_posts,
                    'orderby'        => 'post__in',
                    'posts_per_page' => -1,
                ];

                foreach ($terms as $term) {
                    $service_args['tax_query'][] = [
                        'taxonomy' => $service_area_slug,
                        'field'    => 'slug',
                        'terms'    => $term->slug,
                    ];
                }
            }else{
                $service_args = [];
            }
            //$check[]  = $post_id;
            //$check[]  = $terms;
            $service_query = new WP_Query($service_args);
            if ($service_query->have_posts()) {
                while ($service_query->have_posts()) {
                    $service_query->the_post();
                    if($post_id == get_the_ID()){
                        continue;
                    }
                    $single_service = [];
                    if($saved_settings['capability-hasOfferCatalog-name']){
                        $field = explode(',', $saved_settings['capability-hasOfferCatalog-name']);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_service['name'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_service['name'] = get_field($field_name);
                        }
                    }
                    if($saved_settings['capability-hasOfferCatalog-description']){
                        $field = explode(',', $saved_settings['capability-hasOfferCatalog-description']);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_service['description'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_service['description'] = get_field($field_name);
                        }
                    }
                    $single_service['URL'] = get_post_permalink(get_the_ID());
                    $service_result[] = $single_service;
                }
                $schema["hasOfferCatalog"] = $service_result;
            }
            wp_reset_postdata();
            

            //Reviews
            $aggregateRating_schema = [];
            $aggregateRating_schema['@type'] = "AggregateRating";
            $total_rating = 0;
            $review_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'review_posttype'
                )
            );
            $review_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT property,  value FROM $table_name WHERE page = %s",
                    'review'
                ),ARRAY_A
            );
            $review_settings = [];
            if ( ! empty( $review_rows ) ) {
                foreach ( $review_rows as $row ) {
                    $review_settings[ $row['property'] ] = $row['value'];
                }
            }
            
            $terms = get_the_terms($post_id, $service_area_slug);
            if(isset($review_post_type)){
                $review_args = [
                    'post_type'      => $review_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'tax_query'      => [
                        'relation' => 'OR', // OR relation between multiple terms
                    ],
                ];
                foreach ($terms as $term_slug) {
                    $review_args['tax_query'][] = [
                        'taxonomy' => $service_area_slug,
                        'field'    => 'slug',
                        'terms'    => $term_slug,
                    ];
                }
                $review_query = new WP_Query($review_args);
                $total_reviews = $review_query->post_count;
                $review_result = generate_review_schema($review_post_type,$review_settings,$review_query);
                $schema['review'] = $review_result;
                if ($review_query->have_posts()) {
                    while ($review_query->have_posts()) {
                        $review_query->the_post();
                        if($review_settings['review-rating']){
                            $field = explode(',', $review_settings['review-rating']);
                            $field_name = $field[0];
                            $field_type = $field[1];
                            if ($field_type == 'built-in') {
                                $total_rating += intval(get_post_field($field_name));
                            } elseif ($field_type == 'ACF') {
                                $total_rating += intval(get_field($field_name));
                            }
                            $single_review["reviewRating"] = $reviewRating;
                        }
                    }
                }
                $aggregateRating_schema['ratingValue'] = $total_rating/$total_reviews;
                $aggregateRating_schema['reviewCount'] = $total_reviews;
                $schema['aggregateRating'] = $aggregateRating_schema;
            }

            //FAQ
            $faq = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq'
                )
            );
            $question = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq-question'
                )
            );
            $answer = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq-answer'
                )
            );
            $faq_schema= get_faq_object($post_id, $faq, $question, $answer);
            update_post_meta($post_id, '_injected_script',  json_encode($schema));
            update_post_meta($post_id, '_injected_faq_script',  json_encode($faq_schema));
            $results[] = json_encode($schema);
        }
        wp_reset_postdata();
        wp_send_json_success([
            'schema' => $results,
            //'testing'=>$posts_query
        ]);
    }
}

//generate schema for service general pages
add_action('wp_ajax_service_general_generate_schema', 'service_general_generate_schema');

function service_general_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';
    $post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_general_posttype'
        )
    );
    $post_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_general_taxonomy'
        )
    );
    $post_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_general_term'
        )
    );
    $service_area_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy_slug'
        )
    );

    $service_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_taxonomy_slug'
        )
    );
    
    $single_address = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s AND property = %s",
            'global',
            'single_location'
        )
    );

    //home page property
    $service_area_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_posttype'
        )
    );

    //service area property
    $service_area_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_posttype'
        )
    );
    $service_area_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy'
        )
    );
    $service_area_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_term'
        )
    );
    $service_area_taxo_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy_slug'
        )
    );

    $manual_service_general_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_general_posts'
        )
    );
    if(isset($manual_service_general_posts)){
        $manual_service_general_posts = json_decode(stripslashes($manual_service_general_posts),true);
    }else{
        $manual_service_general_posts = [];
    }


    //fetch all posts
    if($post_type!=""){
        $post_args = [
            'post_type'=> $post_type,
            'posts_per_page' => -1,
            'status' => 'publish'
        ];

        if ($post_taxo && $post_term){
            $post_args["tax_query"] =[ [
                'taxonomy' => $post_taxo,  
                'field'    => 'id',
                'terms'    => $post_term
            ]];
        }
    }elseif(isset($manual_service_general_posts) && $manual_service_general_posts!=[]){
        $post_args = [
            'post_type' => 'any',
            'post__in'       => $manual_service_general_posts,
            'orderby'        => 'post__in',
            'posts_per_page' => -1
        ];
    }else{
        $post_args = [];
    }

    $posts_query = new WP_Query($post_args);

    $results = [];

    $check = [];

    //Properties from home page
    $businessType = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'businessType'
        )
    );

    $home_logo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'logo'
        )
    );
    $home_priceRange = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'priceRange'
        )
    );
    $home_streetAddress = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'streetAddress'
        )
    );
    $home_addressLocality = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'addressLocality'
        )
    );
    $home_addressRegion = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'addressRegion'
        )
    );
    $home_postalCode = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'postalCode'
        )
    );
    $home_hasStreetAddress = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'hasStreetAddress'
        )
    );
    $home_telephone = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'telephone'
        )
    );
    $home_monday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'monday'
        )
    );
    $home_tuesday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'tuesday'
        )
    );
    $home_wednesday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'wednesday'
        )
    );
    $home_thursday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'thursday'
        )
    );
    $home_friday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'friday'
        )
    );
    $home_saturday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'saturday'
        )
    );
    $home_sunday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'sunday'
        )
    );


    //fetch schema setting
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'service-general'
        ),
        ARRAY_A
    );

    $saved_settings = [];
    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            $saved_settings[ $row['property'] ] = $row['value'];
        }
    }

    
    $manual_service_area_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_area_posts'
        )
    );
    if(isset($manual_service_area_posts)){
        $manual_service_area_posts = json_decode(stripslashes($manual_service_area_posts),true);
    }else{
        $manual_service_area_posts = [];
    }


    //loop through posts and generate schema for each post
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();  
            $post_id = get_the_ID();
            $schema = [];
            $schema["@context"] = "https://schema.org";
            //properties from home page
            if($businessType){
                $schema["@type"] = "Product";
            }
            if($home_logo){
                $schema["logo"] = $home_logo;
            }
            if($home_priceRange){
                $schema["priceRange"] = str_repeat('$', intval($home_priceRange)); ;
            }

            //properties from service area pagae
            if($single_address){
                $address = [];
                if($home_hasStreetAddress && $home_streetAddress){
                    $address['streetAddress'] = $home_streetAddress;
                }
                if($home_addressLocality){
                    $address['addressLocality'] = $home_addressLocality;
                }
                if($home_addressRegion){
                    $address['addressRegion'] = $home_addressRegion;
                }
                if($home_postalCode){
                    $address['postalCode'] = $home_postalCode;
                }
                $schema['address'] = $address;
                if($home_telephone){
                    $schema['telephone'] = explode(',',$home_telephone);
                }
                $hours = [];
                if($home_monday){
                    $hours[] = 'mo ' . $home_monday;
                }
                if($home_tuesday){
                    $hours[] = 'tu ' . $home_tuesday;
                }
                if($home_wednesday){
                    $hours[] = 'we ' . $home_wednesday;
                }
                if($home_thursday){
                    $hours[] = 'th ' . $home_thursday;
                }
                if($home_friday){
                    $hours[] = 'fr ' . $home_friday;
                }
                if($home_saturday){
                    $hours[] = 'sa ' . $home_saturday;
                }
                if($home_sunday){
                    $hours[] = 'su ' . $home_sunday;
                }
                $schema['openingHours'] = $hours;
            }else{
                $service_area_terms = get_the_terms( $post_id, $service_area_taxo_slug );
                if($service_area_post_type!=""){
                    $service_area_args = [
                        'post_type'      => $service_area_post_type,
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'tax_query'      => [
                            'relation' => 'AND',
                            [
                                'taxonomy' => $service_area_taxo,
                                'field'    => 'id',
                                'terms'    => $service_area_term,
                            ],
                            [
                                'taxonomy' => $service_area_taxo_slug,
                                'field'    => 'id',
                                'terms'    => $service_area_terms[0]->term_id,
                            ],
                        ]
                    ];
                }elseif(isset($manual_service_area_posts) && $manual_service_area_posts!=[]){
                    $service_area_args = [
                        'post_type' => 'any',
                        'post__in'       => $manual_service_area_posts,
                        'orderby'        => 'post__in',
                        'posts_per_page' => -1
                    ];
                }else{
                    $service_area_args = [];
                }
                
                $service_area_query = new WP_Query($service_area_args);
                $service_area_id = $service_area_query->posts[0];
                $service_area_has_street_address = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'hasStreetAddress'
                    )
                );
                $service_area_has_street_address = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'hasStreetAddress'
                    )
                );
                $service_area_street_address = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-street-address'
                    )
                );
                $service_area_city = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-city'
                    )
                );
                $service_area_province = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-province'
                    )
                );
                $service_area_postal_code = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-postal-code'
                    )
                );
                $service_area_areaserved_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-street-areaserved-id'
                    )
                );
                $service_area_telephone = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-telephone'
                    )
                );
                $service_area_monday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-monday'
                    )
                );
                $service_area_tuesday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-tuesday'
                    )
                );
                $service_area_wednesday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-wednesday'
                    )
                );
                $service_area_thursday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-thursday'
                    )
                );
                $service_area_friday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-friday'
                    )
                );
                $service_area_saturday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-saturday'
                    )
                );
                $service_area_sunday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-sunday'
                    )
                );
                //address
                $address=[];
                
                if($service_area_has_street_address){
                    if($service_area_street_address){
                        $field = explode(',', $service_area_street_address);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $address['streetAddress'] = get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $address['streetAddress'] = get_field($field_name,$service_area_id);
                        }
                    }
                }
                
                if($service_area_city){
                    $field = explode(',', $service_area_city);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['addressLocality'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $address['addressLocality'] = get_field($field_name,$service_area_id);
                    }
                }
                if($service_area_province){
                    $field = explode(',', $service_area_province);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['addressRegion'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $address['addressRegion'] = get_field($field_name,$service_area_id);
                    }
                }
                if($service_area_postal_code){
                    $field = explode(',', $service_area_postal_code );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['postalCode'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $address['postalCode'] = get_field($field_name,$service_area_id);
                    }
                }

                $schema['address'] = $address;
                $areaserved_schema = [];
                if( $service_area_city){
                    $field = explode(',', $service_area_city );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $areaserved_schema['addressLocality'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $areaserved_schema['addressLocality'] = get_field($field_name,$service_area_id);
                    }
                }
                if( $service_area_areaserved_id){
                    $field = explode(',', $service_area_areaserved_id );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $areaserved_schema['id'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $areaserved_schema['id'] = get_field($field_name,$service_area_id);
                    }
                }
                $schema['areaServed'] = $areaserved_schema;
                if( $service_area_telephone){
                    $field = explode(',', $service_area_telephone );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $schema['telephone'] = explode(',',get_post_field($field_name,$service_area_id));
                    } elseif ($field_type == 'ACF') {
                        $schema['telephone'] = explode(',',get_field($field_name,$service_area_id));
                    }
                }
                $hours = [];
                if($service_area_monday||$service_area_tuesday||$service_area_wednesday||$service_area_thursday||$service_area_friday||$service_area_saturday||$service_area_sunday){
                    if( $service_area_monday){
                        $field = explode(',', $service_area_monday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'mo ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'mo ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_tuesday){
                        $field = explode(',', $service_area_tuesday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'tu ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'tu ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_wednesday){
                        $field = explode(',', $service_area_wednesday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'we ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'we ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_thursday){
                        $field = explode(',', $service_area_thursday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'th ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'th ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_friday){
                        $field = explode(',', $service_area_friday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'fr ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'fr ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_saturday){
                        $field = explode(',', $service_area_saturday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'sa ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'sa ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_sunday){
                        $field = explode(',', $service_area_sunday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'su ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'su ' . get_field($field_name,$service_area_id);
                        }
                    }
                }else{
                    if($home_monday){
                        $hours[] = 'mo ' . $home_monday;
                    }
                    if($home_tuesday){
                        $hours[] = 'tu ' . $home_tuesday;
                    }
                    if($home_wednesday){
                        $hours[] = 'we ' . $home_wednesday;
                    }
                    if($home_thursday){
                        $hours[] = 'th ' . $home_thursday;
                    }
                    if($home_friday){
                        $hours[] = 'fr ' . $home_friday;
                    }
                    if($home_saturday){
                        $hours[] = 'sa ' . $home_saturday;
                    }
                    if($home_sunday){
                        $hours[] = 'su ' . $home_sunday;
                    }
                }
                $schema['openingHours'] = $hours;
               
            }

            //name
            if($saved_settings['service-general-name']){
                $field = explode(',', $saved_settings['service-general-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['name'] = get_field($field_name);
                }
            }
            //description
            if($saved_settings['service-general-description']){
                $field = explode(',', $saved_settings['service-general-description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['description'] = get_field($field_name);
                }
            }
            //keywords
            if($saved_settings['service-general-keywords']){
                $field = explode(',', $saved_settings['service-general-keywords']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['keywords'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['keywords'] = get_field($field_name);
                }
            }
            
            //employee
            $employee_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'employee_posttype'
                )
            );
            $employee_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT property,  value FROM $table_name WHERE page = %s",
                    'employee'
                ),ARRAY_A
            );
            $employee_settings = [];
            if ( ! empty( $employee_rows ) ) {
                foreach ( $employee_rows as $row ) {
                    $employee_settings[ $row['property'] ] = $row['value'];
                }
            }
            $terms = get_the_terms($post_id, $service_slug);
            if(isset($employee_post_type)){
                $employee_args = [
                    'post_type'      => $employee_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'tax_query'      => [
                        'relation' => 'OR', // OR relation between multiple terms
                    ],
                ];
                 foreach ($terms as $term_slug) {
                    $employee_args['tax_query'][] = [
                        'taxonomy' => $service_slug,
                        'field'    => 'slug',
                        'terms'    => $term_slug,
                    ];
                }
                $employee_query = new WP_Query($employee_args);
                $employee_result = generate_employee_schema($employee_post_type,$employee_settings,$employee_query );
                $schema['employee'] = $employee_result;
            }
           
            //hasOfferCatelog
            $service_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'service_general_posttype'
                )
            );
            $terms = get_the_terms($post_id, $service_area_slug);
            $service_terms = get_the_terms($post_id, $post_taxo);
            $service_type_slug=  $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'service_general_taxonomy'
                )
            );
            $service_type_term_slug = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'service_general_term'
                )
            );
            
            $manual_service_capability_posts = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'manual_service_capability_posts'
                )
            );
            if(isset($manual_service_capability_posts)){
                $manual_service_capability_posts = json_decode(stripslashes($manual_service_capability_posts),true);
            }else{
                $manual_service_capability_posts = [];
            }

            $service_result = [];

            if ( isset( $service_post_type ) ) {
                $service_args = [
                    'post_type'      => $service_post_type,
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'tax_query'      => [
                        'relation' => 'AND', // AND between $terms and $service_terms
                    ],
                ];

                // Nested OR for $terms
                if ( ! empty( $terms ) ) {
                    $term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $terms as $term_obj ) {
                        $term_tax_query[] = [
                            'taxonomy' => $service_area_slug,
                            'field'    => 'slug',
                            'terms'    => $term_obj->slug,
                        ];
                    }
                    $service_args['tax_query'][] = $term_tax_query;
                }
                // Nested OR for $service_terms
                if ( ! empty( $service_terms ) ) {
                    $service_term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $service_terms as $service_term_obj ) {
                        $service_term_tax_query[] = [
                            'taxonomy' => $post_taxo,
                            'field'    => 'slug',
                            'terms'    => $service_term_obj->slug,
                            'operator' => 'NOT IN',
                        ];
                    }
                    $service_args['tax_query'][] = $service_term_tax_query;
                }
                $service_area_term_tax_query = [
                    'relation' => 'OR',
                ];
                $service_area_term_tax_query[] = [
                    'taxonomy' => $service_type_slug,
                    'field'    => 'id',
                    'terms'    => $service_type_term_slug,
                    'operator' => 'NOT IN',
                ];
                $service_args['tax_query'][] = $service_area_term_tax_query;

            }elseif(isset($manual_service_capability_posts) && $manual_service_capability_posts!=[]){
                $service_args = [
                    'post_type' => 'any',
                    'post__in'       => $manual_service_capability_posts,
                    'orderby'        => 'post__in',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                ];
                // Nested OR for $terms
                if ( ! empty( $terms ) ) {
                    $term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $terms as $term_obj ) {
                        $term_tax_query[] = [
                            'taxonomy' => $service_area_slug,
                            'field'    => 'slug',
                            'terms'    => $term_obj->slug,
                        ];
                    }
                    $service_args['tax_query'][] = $term_tax_query;
                }
            }else{
                $service_args = [];
            }


            
            $check[] = $service_args;
            $service_query = new WP_Query( $service_args );
            if ($service_query->have_posts()) {
                while ($service_query->have_posts()) {
                    $service_query->the_post();
                    if($post_id == get_the_ID()){
                        continue;
                    }
                    $single_service = [];
                    if($saved_settings['capability-hasOfferCatalog-name']){
                        $field = explode(',', $saved_settings['capability-hasOfferCatalog-name']);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_service['name'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_service['name'] = get_field($field_name);
                        }
                    }
                    if($saved_settings['capability-hasOfferCatalog-description']){
                        $field = explode(',', $saved_settings['capability-hasOfferCatalog-description']);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_service['description'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_service['description'] = get_field($field_name);
                        }
                    }
                    $single_service['URL'] = get_post_permalink(get_the_ID());
                    $service_result[] = $single_service;
                }
                $schema["hasOfferCatalog"] = $service_result;
            }
            wp_reset_postdata();
            //Reviews
            $aggregateRating_schema = [];
            $aggregateRating_schema['@type'] = "AggregateRating";
            $total_rating = 0;
            $review_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'review_posttype'
                )
            );
            $review_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT property,  value FROM $table_name WHERE page = %s",
                    'review'
                ),ARRAY_A
            );
            $review_settings = [];
            if ( ! empty( $review_rows ) ) {
                foreach ( $review_rows as $row ) {
                    $review_settings[ $row['property'] ] = $row['value'];
                }
            }

            $terms = get_the_terms($post_id, $service_area_slug);
            $service_terms = get_the_terms($post_id, $service_slug);
            if(isset($review_post_type)){
                $review_args = [
                    'post_type'      => $review_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'tax_query'      => [
                        'relation' => 'AND', // OR relation between multiple terms
                    ],
                ];
                if ( ! empty( $terms ) ) {
                    $term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $terms as $term ) {
                        $term_tax_query[] = [
                            'taxonomy' => $service_area_slug,
                            'field'    => 'slug',
                            'terms'    => $term->slug,
                        ];
                    }
                    $review_args['tax_query'][] = $term_tax_query;
                }
                if ( ! empty( $service_terms ) ) {
                    $service_term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $service_terms as $term ) {
                        $service_term_tax_query[] = [
                            'taxonomy' => $service_slug,
                            'field'    => 'slug',
                            'terms'    => $term->slug,
                        ];
                    }
                    $review_args['tax_query'][] = $service_term_tax_query;
                }
                $check[] = $review_args;
                $review_query = new WP_Query($review_args);
                $total_reviews = $review_query->post_count;
                $review_result = generate_review_schema($review_post_type,$review_settings,$review_query);
                $schema['review'] = $review_result;
                if ($review_query->have_posts()) {
                    while ($review_query->have_posts()) {
                        $review_query->the_post();
                        if($review_settings['review-rating']){
                            $field = explode(',', $review_settings['review-rating']);
                            $field_name = $field[0];
                            $field_type = $field[1];
                            if ($field_type == 'built-in') {
                                $total_rating += intval(get_post_field($field_name));
                            } elseif ($field_type == 'ACF') {
                                $total_rating += intval(get_field($field_name));
                            }
                            $single_review["reviewRating"] = $reviewRating;
                        }
                    }
                }
                $aggregateRating_schema['ratingValue'] = $total_rating/$total_reviews;
                $aggregateRating_schema['reviewCount'] = $total_reviews;
                $schema['aggregateRating'] = $aggregateRating_schema;
            }
            //FAQ
            $faq = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq'
                )
            );
            $question = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq-question'
                )
            );
            $answer = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq-answer'
                )
            );
          
            $faq_schema= get_faq_object($post_id, $faq, $question, $answer);
            update_post_meta($post_id, '_injected_script',  json_encode($schema));
            update_post_meta($post_id, '_injected_faq_script',  json_encode($faq_schema));
            $results[] = json_encode($schema);
        }
        wp_reset_postdata();
        wp_send_json_success([
            'schema' => $results,
            //'test' => $service_args
            // "posts" =>$posts_query,
            // "review"=>$review_post_type,
            // "check" => $check,
        ]);
    }
}

//generate schema for service capability pages

add_action('wp_ajax_service_capability_generate_schema', 'service_capability_generate_schema');

function service_capability_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';
    $post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_posttype'
        )
    );
    $post_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_taxonomy'
        )
    );
    $post_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_term'
        )
    );
    $service_area_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy_slug'
        )
    );

    $service_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_taxonomy_slug'
        )
    );
    
    $single_address = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s AND property = %s",
            'global',
            'single_location'
        )
    );

    //home page property
    $service_area_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_posttype'
        )
    );

    //service area property
    $service_area_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_posttype'
        )
    );
    $service_area_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy'
        )
    );
    $service_area_term = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_term'
        )
    );
    $service_area_taxo_slug = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_area_taxonomy_slug'
        )
    );

    
    $manual_service_capability_posts = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'manual_service_capability_posts'
        )
    );
    if(isset($manual_service_capability_posts)){
        $manual_service_capability_posts = json_decode(stripslashes($manual_service_capability_posts),true);
    }else{
        $manual_service_capability_posts = [];
    }

    //fetch all posts
    if($post_type!=""){
        $post_args = [
            'post_type'=> $post_type,
            'posts_per_page' => -1,
            'status' => 'publish'
        ];

        if ($post_taxo && $post_term){
            $post_args["tax_query"] =[ [
                'taxonomy' => $post_taxo,  
                'field'    => 'id',
                'terms'    => $post_term
            ]];
        }
    }elseif(isset($manual_service_capability_posts) && $manual_service_capability_posts!=[]){
        $post_args = [
            'post_type' => 'any',
            'post__in'       => $manual_service_capability_posts,
            'orderby'        => 'post__in',
            'posts_per_page' => -1
        ];
    }else{
        $post_args = [];
    }

    $posts_query = new WP_Query($post_args);

    $results = [];

    $check = [];

    //Properties from home page
    $businessType = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'businessType'
        )
    );

    $home_logo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'logo'
        )
    );
    $home_priceRange = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'priceRange'
        )
    );
    $home_streetAddress = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'streetAddress'
        )
    );
    $home_addressLocality = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'addressLocality'
        )
    );
    $home_addressRegion = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'addressRegion'
        )
    );
    $home_postalCode = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'postalCode'
        )
    );
    $home_hasStreetAddress = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'hasStreetAddress'
        )
    );
    $home_telephone = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'telephone'
        )
    );
    $home_monday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'monday'
        )
    );
    $home_tuesday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'tuesday'
        )
    );
    $home_wednesday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'wednesday'
        )
    );
    $home_thursday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'thursday'
        )
    );
    $home_friday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'friday'
        )
    );
    $home_saturday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'saturday'
        )
    );
    $home_sunday = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'home_page',
            'sunday'
        )
    );


    //fetch schema setting
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'service-general'
        ),
        ARRAY_A
    );

    $saved_settings = [];
    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            $saved_settings[ $row['property'] ] = $row['value'];
        }
    }

    //loop through posts and generate schema for each post
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();  
            $post_id = get_the_ID();
            $schema = [];
            $schema["@context"] = "https://schema.org";
            //properties from home page
            if($businessType){
                $schema["@type"] = "Product";
            }
            if($home_logo){
                $schema["logo"] = $home_logo;
            }
            if($home_priceRange){
                $schema["priceRange"] = str_repeat('$', intval($home_priceRange)); ;
            }

            //properties from service area pagae
            if($single_address){
                $address = [];
                if($home_hasStreetAddress && $home_streetAddress){
                    $address['streetAddress'] = $home_streetAddress;
                }
                if($home_addressLocality){
                    $address['addressLocality'] = $home_addressLocality;
                }
                if($home_addressRegion){
                    $address['addressRegion'] = $home_addressRegion;
                }
                if($home_postalCode){
                    $address['postalCode'] = $home_postalCode;
                }
                $schema['address'] = $address;
                if($home_telephone){
                    $schema['telephone'] = explode(',',$home_telephone);
                }
                $hours = [];
                if($home_monday){
                    $hours[] = 'mo ' . $home_monday;
                }
                if($home_tuesday){
                    $hours[] = 'tu ' . $home_tuesday;
                }
                if($home_wednesday){
                    $hours[] = 'we ' . $home_wednesday;
                }
                if($home_thursday){
                    $hours[] = 'th ' . $home_thursday;
                }
                if($home_friday){
                    $hours[] = 'fr ' . $home_friday;
                }
                if($home_saturday){
                    $hours[] = 'sa ' . $home_saturday;
                }
                if($home_sunday){
                    $hours[] = 'su ' . $home_sunday;
                }
                $schema['openingHours'] = $hours;
            }else{
                $service_area_terms = get_the_terms( $post_id, $service_area_taxo_slug );
                $service_area_args = [
                    'post_type'      => $service_area_post_type,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'tax_query'      => [
                        'relation' => 'AND',
                        [
                            'taxonomy' => $service_area_taxo,
                            'field'    => 'id',
                            'terms'    => $service_area_term,
                        ],
                        [
                            'taxonomy' => $service_area_taxo_slug,
                            'field'    => 'id',
                            'terms'    => $service_area_terms[0]->term_id,
                        ],
                    ]
                ];
                $service_area_query = new WP_Query($service_area_args);
                $service_area_id = $service_area_query->posts[0];
                $service_area_has_street_address = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'hasStreetAddress'
                    )
                );
                $service_area_has_street_address = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'hasStreetAddress'
                    )
                );
                $service_area_street_address = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-street-address'
                    )
                );
                $service_area_city = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-city'
                    )
                );
                $service_area_province = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-province'
                    )
                );
                $service_area_postal_code = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-postal-code'
                    )
                );
                $service_area_areaserved_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-street-areaserved-id'
                    )
                );
                $service_area_telephone = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-telephone'
                    )
                );
                $service_area_monday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-monday'
                    )
                );
                $service_area_tuesday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-tuesday'
                    )
                );
                $service_area_wednesday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-wednesday'
                    )
                );
                $service_area_thursday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-thursday'
                    )
                );
                $service_area_friday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-friday'
                    )
                );
                $service_area_saturday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-saturday'
                    )
                );
                $service_area_sunday = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT value FROM $table_name WHERE page = %s and property = %s",
                        'service-area',
                        'service-area-sunday'
                    )
                );
                //address
                $address=[];
                
                if($service_area_has_street_address){
                    if($service_area_street_address){
                        $field = explode(',', $service_area_street_address);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $address['streetAddress'] = get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $address['streetAddress'] = get_field($field_name,$service_area_id);
                        }
                    }
                }
                
                if($service_area_city){
                    $field = explode(',', $service_area_city);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['addressLocality'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $address['addressLocality'] = get_field($field_name,$service_area_id);
                    }
                }
                if($service_area_province){
                    $field = explode(',', $service_area_province);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['addressRegion'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $address['addressRegion'] = get_field($field_name,$service_area_id);
                    }
                }
                if($service_area_postal_code){
                    $field = explode(',', $service_area_postal_code );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $address['postalCode'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $address['postalCode'] = get_field($field_name,$service_area_id);
                    }
                }

                $schema['address'] = $address;
                $areaserved_schema = [];
                if( $service_area_city){
                    $field = explode(',', $service_area_city );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $areaserved_schema['addressLocality'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $areaserved_schema['addressLocality'] = get_field($field_name,$service_area_id);
                    }
                }
                if( $service_area_areaserved_id){
                    $field = explode(',', $service_area_areaserved_id );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $areaserved_schema['id'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $areaserved_schema['id'] = get_field($field_name,$service_area_id);
                    }
                }
                $schema['areaServed'] = $areaserved_schema;
                if( $service_area_telephone){
                    $field = explode(',', $service_area_telephone );
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $schema['telephone'] = explode(',',get_post_field($field_name,$service_area_id));
                    } elseif ($field_type == 'ACF') {
                        $schema['telephone'] = explode(',',get_field($field_name,$service_area_id));
                    }
                }
                $hours = [];
                if($service_area_monday||$service_area_tuesday||$service_area_wednesday||$service_area_thursday||$service_area_friday||$service_area_saturday||$service_area_sunday){
                    if( $service_area_monday){
                        $field = explode(',', $service_area_monday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'mo ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'mo ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_tuesday){
                        $field = explode(',', $service_area_tuesday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'tu ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'tu ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_wednesday){
                        $field = explode(',', $service_area_wednesday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'we ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'we ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_thursday){
                        $field = explode(',', $service_area_thursday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'th ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'th ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_friday){
                        $field = explode(',', $service_area_friday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'fr ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'fr ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_saturday){
                        $field = explode(',', $service_area_saturday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'sa ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'sa ' . get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_sunday){
                        $field = explode(',', $service_area_sunday );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $hours[] = 'su ' . get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $hours[] = 'su ' . get_field($field_name,$service_area_id);
                        }
                    }
                }else{
                    if($home_monday){
                        $hours[] = 'mo ' . $home_monday;
                    }
                    if($home_tuesday){
                        $hours[] = 'tu ' . $home_tuesday;
                    }
                    if($home_wednesday){
                        $hours[] = 'we ' . $home_wednesday;
                    }
                    if($home_thursday){
                        $hours[] = 'th ' . $home_thursday;
                    }
                    if($home_friday){
                        $hours[] = 'fr ' . $home_friday;
                    }
                    if($home_saturday){
                        $hours[] = 'sa ' . $home_saturday;
                    }
                    if($home_sunday){
                        $hours[] = 'su ' . $home_sunday;
                    }
                }
                $schema['openingHours'] = $hours;
               
            }

            //name
            if($saved_settings['service-general-name']){
                $field = explode(',', $saved_settings['service-general-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['name'] = get_field($field_name);
                }
            }
            //description
            if($saved_settings['service-general-description']){
                $field = explode(',', $saved_settings['service-general-description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['description'] = get_field($field_name);
                }
            }
            //keywords
            if($saved_settings['service-general-keywords']){
                $field = explode(',', $saved_settings['service-general-keywords']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['keywords'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['keywords'] = get_field($field_name);
                }
            }
            
            //employee
            $employee_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'employee_posttype'
                )
            );
            $employee_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT property,  value FROM $table_name WHERE page = %s",
                    'employee'
                ),ARRAY_A
            );
            $employee_settings = [];
            if ( ! empty( $employee_rows ) ) {
                foreach ( $employee_rows as $row ) {
                    $employee_settings[ $row['property'] ] = $row['value'];
                }
            }
            $terms = get_the_terms($post_id, $service_slug);
            if(isset($employee_post_type)){
                $employee_args = [
                    'post_type'      => $employee_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'tax_query'      => [
                        'relation' => 'OR', // OR relation between multiple terms
                    ],
                ];
                 foreach ($terms as $term_slug) {
                    $employee_args['tax_query'][] = [
                        'taxonomy' => $service_slug,
                        'field'    => 'slug',
                        'terms'    => $term_slug,
                    ];
                }
                $employee_query = new WP_Query($employee_args);
                $employee_result = generate_employee_schema($employee_post_type,$employee_settings,$employee_query );
                $schema['employee'] = $employee_result;
            }

            //Reviews
            $aggregateRating_schema = [];
            $aggregateRating_schema['@type'] = "AggregateRating";
            $total_rating = 0;
            $review_post_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'global',
                    'review_posttype'
                )
            );
            $review_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT property,  value FROM $table_name WHERE page = %s",
                    'review'
                ),ARRAY_A
            );
            $review_settings = [];
            if ( ! empty( $review_rows ) ) {
                foreach ( $review_rows as $row ) {
                    $review_settings[ $row['property'] ] = $row['value'];
                }
            }

            $terms = get_the_terms($post_id, $service_area_slug);
            $service_terms = get_the_terms($post_id, $service_slug);
            if(isset($review_post_type)){
                $review_args = [
                    'post_type'      => $review_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'tax_query'      => [
                        'relation' => 'AND', // OR relation between multiple terms
                    ],
                ];
                if ( ! empty( $terms ) ) {
                    $term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $terms as $term ) {
                        $term_tax_query[] = [
                            'taxonomy' => $service_area_slug,
                            'field'    => 'slug',
                            'terms'    => $term->slug,
                        ];
                    }
                    $review_args['tax_query'][] = $term_tax_query;
                }
                if ( ! empty( $service_terms ) ) {
                    $service_term_tax_query = [
                        'relation' => 'OR',
                    ];
                    foreach ( $service_terms as $term ) {
                        $service_term_tax_query[] = [
                            'taxonomy' => $service_slug,
                            'field'    => 'slug',
                            'terms'    => $term->slug,
                        ];
                    }
                    $review_args['tax_query'][] = $service_term_tax_query;
                }
                $check[] = $review_args;
                $review_query = new WP_Query($review_args);
                $total_reviews = $review_query->post_count;
                $review_result = generate_review_schema($review_post_type,$review_settings,$review_query);
                $schema['review'] = $review_result;
                if ($review_query->have_posts()) {
                    while ($review_query->have_posts()) {
                        $review_query->the_post();
                        if($review_settings['review-rating']){
                            $field = explode(',', $review_settings['review-rating']);
                            $field_name = $field[0];
                            $field_type = $field[1];
                            if ($field_type == 'built-in') {
                                $total_rating += intval(get_post_field($field_name));
                            } elseif ($field_type == 'ACF') {
                                $total_rating += intval(get_field($field_name));
                            }
                            $single_review["reviewRating"] = $reviewRating;
                        }
                    }
                }
                $aggregateRating_schema['ratingValue'] = $total_rating/$total_reviews;
                $aggregateRating_schema['reviewCount'] = $total_reviews;
                $schema['aggregateRating'] = $aggregateRating_schema;
            }
            //FAQ
            $faq = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq'
                )
            );
            $question = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq-question'
                )
            );
            $answer = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM $table_name WHERE page = %s and property = %s",
                    'faq',
                    'faq-answer'
                )
            );
            $faq_schema= get_faq_object($post_id, $faq, $question, $answer);
            update_post_meta($post_id, '_injected_script',  json_encode($schema));
            update_post_meta($post_id, '_injected_faq_script',  json_encode($faq_schema));
            $results[] = json_encode($schema);
        }
        wp_reset_postdata();
        wp_send_json_success([
            'schema' => $results,
            "test"=>$service_area_slug,
        ]);
    }
}

//generate employee schema
function generate_employee_schema($employee_post_type, $employee_settings, $posts_query){
    $employee_result = [];
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post(); 
            $single_employee = [];
            $single_employee['@type'] = "Person";
            if($employee_settings['employee-name']){
                $field = explode(',', $employee_settings['employee-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_employee['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_employee['name'] = get_field($field_name);
                }
            }
            if($employee_settings['employee-job-title']){
                $field = explode(',', $employee_settings['employee-job-title']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_employee['jobTitle'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_employee['jobTitle'] = get_field($field_name);
                }
            }
            if($employee_settings['employee-description']){
                $field = explode(',', $employee_settings['employee-description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_employee['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_employee['description'] = get_field($field_name);
                }
            }
            if($employee_settings["employee-credential"]){
                $credential = get_field($employee_settings["employee-credential"]); 
                $result = [];

                if( $credential && is_array($credential) ) {
                    foreach( $credential as $row ) {
                        $result[] = [
                            '@type' => 'EducationalOccupationalCredential',
                            'competencyRequired' =>  $row[$employee_settings["employee-compenency-required"]],
                            'credentialCategory' => $row[$employee_settings["employee-credential-category"]],
                            'recognizedBy'   => [
                                "@type" => "Organization",
                                "name" =>$row[$employee_settings["employee-reconizedby"]],
                            ]
                        ];
                    }
                }
                $single_employee['hasCredential'] = $result;
            }

            if($employee_settings["employee-certification"]){
                $certification = get_field($employee_settings["employee-certification"]); 
                $certification_result = [];

                if( $certification && is_array($certification) ) {
                    foreach( $certification as $row ) {
                        $certification_result[] = [
                            '@type' => 'EducationalOccupationalCredential',
                            'certificationIdentification' =>  $row[$employee_settings["employee-certification-identification"]],
                            'issuedBy'   => [
                                "@type" => "Organization",
                                "name" =>$row[$employee_settings["employee-issuedby"]],
                            ]
                        ];
                    }
                }
                $single_employee['hasCredential'] = $result;
                $single_employee['hasCertification'] = $certification_result;
            }

            if($employee_settings["employee-certification-identification"]){

            }
            if($employee_settings["employee-issuedby"]){

            }
            $employee_result[] = $single_employee;
        }
    }
    return $employee_result;
}
//generate review schema
function generate_review_schema($review_post_type, $review_settings, $reviews_query){
    $review_result = [];
    if ($reviews_query->have_posts()) {
        while ($reviews_query->have_posts()) {
            $reviews_query->the_post();  
            $single_review = [];
            $single_review['@type'] = "Review";
            if($review_settings['review-body']){
                $field = explode(',', $review_settings['review-body']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_review['reviewBody'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_review['reviewBody'] = get_field($field_name);
                }
            }
            if($review_settings['review-rating']){
                $field = explode(',', $review_settings['review-rating']);
                $field_name = $field[0];
                $field_type = $field[1];
                $reviewRating = [];
                $reviewRating["@type"] = "Rating";
                $reviewRating["bestRating"] = "5";
                $reviewRating["worstRating"] = "1";
                $reviewRating["@type"] = "Rating";
                if ($field_type == 'built-in') {
                    $reviewRating["ratingValue"] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $reviewRating["ratingValue"] = get_field($field_name);
                }
                $single_review["reviewRating"] = $reviewRating;
            }
            if($review_settings['review-author']){
                $field = explode(',', $review_settings['review-author']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_review['author'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $single_review['author'] = get_field($field_name);
                }
            }
            if($review_settings['review-date-published']){
                $field = explode(',', $review_settings['review-date-published']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $single_review['datePublished'] =  date("Y-m-d", strtotime(get_post_field($field_name)));
                } elseif ($field_type == 'ACF') {
                    $single_review['datePublished'] =  date("Y-m-d", strtotime(get_field($field_name)));
                }
            }
            $review_result[] = $single_review;
        }
    }
    return $review_result;
}

// fetch repeater fields
add_action('wp_ajax_get_acf_repeaters', function() {
    $post_type = sanitize_text_field($_POST['post_type'] ?? '');

    $groups = $post_type ? acf_get_field_groups(['post_type' => $post_type]) : acf_get_field_groups();
    $repeaters = [];

    foreach ($groups as $group) {
        $fields = acf_get_fields($group['key']);
        if (!$fields) continue;

        foreach ($fields as $field) {
            if ($field['type'] === 'repeater') {
                $repeaters[] = [
                    'label' => $field['label'],
                    'name'  => $field['name'],
                ];
            }
        }
    }

    wp_send_json_success($repeaters);
});


// fetch repeater's subfields
add_action('wp_ajax_get_acf_subfields', function() {
    $post_type = sanitize_text_field($_POST['post_type'] ?? '');
    $repeater_name = sanitize_text_field($_POST['repeater_name'] ?? '');
    if (!$repeater_name) wp_send_json_error('Missing repeater_name');

    $groups = $post_type ? acf_get_field_groups(['post_type' => $post_type]) : acf_get_field_groups();
    $subfields = [];

    foreach ($groups as $group) {
        $fields = acf_get_fields($group['key']);
        if (!$fields) continue;

        foreach ($fields as $field) {
            if ($field['type'] === 'repeater' && $field['name'] === $repeater_name) {
                foreach ($field['sub_fields'] as $sub) {
                    $subfields[] = [
                        'label' => $sub['label'],
                        'name'  => $sub['name'],
                    ];
                }
            }
        }
    }

    wp_send_json_success($subfields);
});

function get_faq_object($post_id, $faq, $question, $answer) {
    if( !function_exists('get_field') ) {
        return [];
    }

    $faq_data = get_field($faq, $post_id); 
    $result = [];

    if( $faq_data && is_array($faq_data) ) {
        foreach( $faq_data as $row ) {
            $result[] = [
                '@type' => 'Question',
                'name' => isset($row[$question]) ? $row[$question] : '',
                'acceptedAnswer'   => [
                    "@type" => "Answer",
                    "text" => isset($row[$answer]) ? $row[$answer] : ''],
            ];
        }
    }

    return [
        '@context' => "https://schema.org",
        '@type' => "FAQPage",
        'mainEntity' => $result
    ];
}