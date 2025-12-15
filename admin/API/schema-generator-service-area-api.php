<?php


//generate schema for service area pages
add_action('wp_ajax_service_area_generate_schema', 'service_area_generate_schema');

function service_area_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';

    
    //fetch global setting
    $global_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'global'
        ),
        ARRAY_A
    );

    $global_settings = [];
    if ( ! empty( $global_rows ) ) {
        foreach ( $global_rows as $row ) {
            $global_settings[ $row['property'] ] = $row['value'];
        }
    }
    $post_type = $global_settings['service_area_posttype'];
    $post_taxo = $global_settings['service_area_taxonomy'];
    $post_term = $global_settings['service_area_term'];
    $service_area_slug = $global_settings['service_area_taxonomy_slug'];
    $manual_service_area_posts = $global_settings['manual_service_area_posts'];
    if(isset($manual_service_area_posts)){
        $manual_service_area_posts = json_decode(stripslashes($manual_service_area_posts),true);
    }else{
        $manual_service_area_posts = [];
    }

    $manual_service_general_posts = $global_settings['manual_service_general_posts'];
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

    //fetch home page setting
    $home_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'home_page'
        ),
        ARRAY_A
    );

    $home_settings = [];
    if ( ! empty( $home_rows ) ) {
        foreach ( $home_rows as $row ) {
            $home_settings[ $row['property'] ] = $row['value'];
        }
    }
    $home_logo =$home_settings['logo'];
    $home_businessType = $home_settings['businessType'];
    $home_businessType_text = $home_settings['businessType-text'];
    $home_priceRange = $home_settings['priceRange'];
    $home_paymentAccepted = $home_settings['paymentAccepted'];
    $home_award = $home_settings['awards'];
    $home_knowsLanguage = $home_settings['knowsLanguage'];
    $home_telephone = $home_settings['telephone'];
    $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    $home_hours = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value 
            FROM $table_name 
            WHERE property IN (" . implode(',', array_fill(0, count($days), '%s')) . ")",
            ...$days
        )
    );

    //fetch service area setting
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
            if($home_businessType_text){
                $schema["@type"] = $home_businessType_text;
            }elseif($home_businessType){
                $schema["@type"] = $home_businessType;
            }
            $url = get_post_permalink($post_id);
            $schema["@id"] = $url . '#localbusiness';
            $schema["url"] = $url;
            //parent organization
            $parentOrganization = [];
            $home_url = home_url();
            $parentOrganization['@type'] = 'Organization';
            $parentOrganization['@id'] = $home_url . '/#localbusiness';
            $parentOrganization['url'] = $home_url;
            $schema['parentOrganization'] = $parentOrganization;
            //sameAs(social media)
            if($home_settings['social-media']){
                $schema['sameAs']=explode(',',$home_settings['social-media']);
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
            if($saved_settings['service-area-street-address']){
                $field = explode(',', $saved_settings['service-area-street-address']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $street_address = get_post_field($field_name);
                    if($street_address){
                        $address['streetAddress'] = $street_address;
                    }
                    
                } elseif ($field_type == 'ACF') {
                    $street_address = get_field($field_name);
                    if($street_address){
                        $address['streetAddress'] = $street_address;
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
                    $amenity_features=explode(',',get_field($field_name));
                    $amenity_schema = [];
                    foreach($amenity_features as $feature){
                        $amenity_schema[] = ["@type"=>"LocationFeatureSpecification","name"=>$feature];
                    }
                    $schema['amenityFeature'] = $amenity_schema;
                }
            }
            //areaServed
            $areaServed=[];
            $areaServed["@type"] = "City";
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
                    $areaServed['sameAs'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $areaServed['sameAs'] = get_field($field_name);
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
            

            //Reviews
            $aggregateRating_schema = get_aggregate_review();
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
                if($total_reviews>0){
                    $review_result = generate_review_schema($review_post_type,$review_settings,$review_query);
                    $schema['review'] = $review_result;
                    // if ($review_query->have_posts()) {
                    //     while ($review_query->have_posts()) {
                    //         $review_query->the_post();
                    //         if($review_settings['review-rating']){
                    //             $field = explode(',', $review_settings['review-rating']);
                    //             $field_name = $field[0];
                    //             $field_type = $field[1];
                    //             if ($field_type == 'built-in') {
                    //                 $total_rating += intval(get_post_field($field_name));
                    //             } elseif ($field_type == 'ACF') {
                    //                 $total_rating += intval(get_field($field_name));
                    //             }
                    //             $single_review["reviewRating"] = $reviewRating;
                    //         }
                    //     }
                    // }
                }
                if(isset($aggregateRating_schema)){
                    $schema['aggregateRating'] = $aggregateRating_schema;
                }
                
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
            'testing'=>$home_settings,
        ]);
    }
}