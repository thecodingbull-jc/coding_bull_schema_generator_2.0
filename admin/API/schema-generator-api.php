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

//Aggregate Review
function get_aggregate_review() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';

    $aggregateRating_schema = [];
    $aggregateRating_schema['@type'] = "AggregateRating";

    $review_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s AND property = %s",
            'global',
            'review_posttype'
        )
    );

    if (!empty($review_post_type)) {

        $total_reviews = wp_count_posts($review_post_type)->publish;

        $aggregateRating_schema['ratingValue'] = 5; 
        $aggregateRating_schema['reviewCount'] = $total_reviews;

        return $aggregateRating_schema;
    }

    return null;
}

//Address List
function get_address_list($service_area_query,$street_address_slug,$city_slug,$province_slug, $postal_slug){
    $address_schema = [];
    if ($service_area_query->have_posts()) {
        while ($service_area_query->have_posts()) {
            $service_area_query->the_post();  
            $post_id = get_the_ID();
            $address=[];
            if($street_address_slug){
                $field = explode(',', $street_address_slug);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $street_address = get_post_field($field_name,$post_id);
                    if($street_address){
                        $address['streetAddress'] = $street_address;
                    }else{
                        continue;
                    }
                    
                } elseif ($field_type == 'ACF') {
                    $street_address = get_field($field_name,$post_id);
                    if($street_address){
                        $address['streetAddress'] = $street_address;
                    }else{
                        continue;
                    }
                }
            }else{
                continue;
            }
            
            if($city_slug){
                $field = explode(',', $city_slug);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $address['addressLocality'] = get_post_field($field_name,$post_id);
                } elseif ($field_type == 'ACF') {
                    $address['addressLocality'] = get_field($field_name,$post_id);
                }
            }
            if($province_slug){
                $field = explode(',', $province_slug);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $address['addressRegion'] = get_post_field($field_name,$post_id);
                } elseif ($field_type == 'ACF') {
                    $address['addressRegion'] = get_field($field_name,$post_id);
                }
            }
            if($postal_slug){
                $field = explode(',', $postal_slug);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $address['postalCode'] = get_post_field($field_name,$post_id);
                } elseif ($field_type == 'ACF') {
                    $address['postalCode'] = get_field($field_name,$post_id);
                }
            }
            $address_schema[] = $address;
        }
    }
    return $address_schema;
}