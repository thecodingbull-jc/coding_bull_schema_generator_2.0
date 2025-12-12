<?php
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
    $service_capability_post_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_posttype'
        )
    );
    $service_capability_taxo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'global',
            'service_capability_taxonomy'
        )
    );
    $service_capability_term = $wpdb->get_var(
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
    
    $service_area_name = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'service-area',
            'service-area-name'
        )
    );

    $service_area_street = $wpdb->get_var(
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
    $service_area_postal = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT value FROM $table_name WHERE page = %s and property = %s",
            'service-area',
            'service-area-postal-code'
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
            'posts_per_page' => 1,//change this
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
            $final_schema = [];
            $final_schema["@context"] = "https://schema.org";
            //properties from home page
            if($businessType){
                $schema["@type"] = "Service";
            }
            if($home_logo){
                $schema["logo"] = $home_logo;
            }
            
            $schema['@id'] = get_post_permalink($post_id) . '#service';
            $schema['url'] = get_post_permalink($post_id);

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

            //Service Type
            if($service_slug){
                $service_type_terms = get_the_terms( $post_id, $service_slug );
                if(isset($service_type_terms)){
                    $schema['serviceType'] = $service_type_terms[0]->name;
                }
            }

            //properties from service area pagae
            if(!$single_address){
                $service_area_terms = get_the_terms( $post_id, $service_area_slug );
                if($service_area_post_type!=""){
                    $service_area_args = [
                        'post_type'      => $service_area_post_type,
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'tax_query'      => [
                            'relation' => 'AND',
                            [
                                'taxonomy' => $service_area_slug,
                                'field'    => 'id',
                                'terms'    => $service_area_terms[0]->term_id,
                            ],
                            [
                                'taxonomy' => $service_area_taxo,
                                'field'    => 'id',
                                'terms'    => $service_area_term,
                            ]
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
                if ($service_area_query->have_posts()) {
                    $service_area_id = $service_area_query->posts[0];
                    $service_area_url = get_post_permalink($service_area_id);
                    $service_area_areaserved_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'service-area',
                            'service-area-street-areaserved-id'
                        )
                    );
                    //provider
                    $schema['provider'] = ["@id" => $service_area_url . '#localbusiness'];
                    //areaServce
                    $areaserved_schema = [];
                    $areaserved_schema["@type"] = "City";
                    if( $service_area_city){
                        $field = explode(',', $service_area_city );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $areaserved_schema['name'] = get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $areaserved_schema['name'] = get_field($field_name,$service_area_id);
                        }
                    }
                    if( $service_area_areaserved_id){
                        $field = explode(',', $service_area_areaserved_id );
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $areaserved_schema['sameAs'] = get_post_field($field_name,$service_area_id);
                        } elseif ($field_type == 'ACF') {
                            $areaserved_schema['sameAs'] = get_field($field_name,$service_area_id);
                        }
                    }
                    $schema['areaServed'] = $areaserved_schema;
                    //address
                    // $address = [];
                    // if($service_area_street){
                    //     $field = explode(',', $service_area_street);
                    //     $field_name = $field[0];
                    //     $field_type = $field[1];
                    //     if ($field_type == 'built-in') {
                    //         $street_address = get_post_field($field_name,$service_area_id);
                    //         if($street_address){
                    //             $address['streetAddress'] = $street_address;
                    //         }
                            
                    //     } elseif ($field_type == 'ACF') {
                    //         $street_address = get_field($field_name,$service_area_id);
                    //         if($street_address){
                    //             $address['streetAddress'] = $street_address;
                    //         }
                    //     }
                    // }
                    
                    // if($service_area_city){
                    //     $field = explode(',', $service_area_city);
                    //     $field_name = $field[0];
                    //     $field_type = $field[1];
                    //     if ($field_type == 'built-in') {
                    //         $address['addressLocality'] = get_post_field($field_name,$service_area_id);
                    //     } elseif ($field_type == 'ACF') {
                    //         $address['addressLocality'] = get_field($field_name,$service_area_id);
                    //     }
                    // }
                    // if($service_area_province){
                    //     $field = explode(',', $service_area_province);
                    //     $field_name = $field[0];
                    //     $field_type = $field[1];
                    //     if ($field_type == 'built-in') {
                    //         $address['addressRegion'] = get_post_field($field_name,$service_area_id);
                    //     } elseif ($field_type == 'ACF') {
                    //         $address['addressRegion'] = get_field($field_name,$service_area_id);
                    //     }
                    // }
                    // if($service_area_postal){
                    //     $field = explode(',', $service_area_postal);
                    //     $field_name = $field[0];
                    //     $field_type = $field[1];
                    //     if ($field_type == 'built-in') {
                    //         $address['postalCode'] = get_post_field($field_name,$service_area_id);
                    //     } elseif ($field_type == 'ACF') {
                    //         $address['postalCode'] = get_field($field_name,$service_area_id);
                    //     }
                    // }

                    // if($address["streetAddress"]){
                    //     $schema['address'] = $address;
                    // }
                    //Branch schema
                    $branch_schema = [];
                    $home_businessType = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'home_page',
                            'businessType'
                        )
                    );
                    $home_businessType_text = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'home_page',
                            'businessType-text'
                        )
                    );
                    if($home_businessType_text){
                        $branch_schema["@type"] = $home_businessType_text;
                    }elseif($home_businessType){
                        $branch_schema["@type"] = $home_businessType;
                    }
                    $field = explode(',', $service_area_name);
                    $field_name = $field[0];
                    $field_type = $field[1];
                    if ($field_type == 'built-in') {
                        $branch_schema['name'] = get_post_field($field_name,$service_area_id);
                    } elseif ($field_type == 'ACF') {
                        $branch_schema['name'] = get_field($field_name,$service_area_id);
                    }
                    $branch_schema["@id"] = $service_area_url . '#localbusiness';
                    $branch_schema["url"] = $service_area_url;
                }
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

            if ( isset( $service_capability_post_type ) ) {
                $service_args = [
                    'post_type'      => $service_capability_post_type,
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
                }
                $service_args['tax_query'][] = $term_tax_query;
                $service_args['tax_query'][] = [
                    'taxonomy' => $service_capability_taxo,
                    'field'    => 'id',
                    'terms'    => $service_capability_term,
                ];

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
            //get related capability services
            $service_query = new WP_Query( $service_args );
            if ($service_query->have_posts()) {
                while ($service_query->have_posts()) {
                    $service_query->the_post();
                    if($post_id == get_the_ID()){
                        continue;
                    }
                    $single_service = [];
                    $single_service['@type'] = 'Service';
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
                    $single_service['@id'] = get_post_permalink(get_the_ID()) . '#service';
                    $single_service['url'] = get_post_permalink(get_the_ID());
                    $service_result[] = $single_service;
                }
                $schema["isRelatedTo"] = $service_result;
            }
            wp_reset_postdata();
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
                $review_query = new WP_Query($review_args);
                $total_reviews = $review_query->post_count;
                if($total_reviews>0){
                    $review_result = generate_review_schema($review_post_type,$review_settings,$review_query);
                    $schema['review'] = $review_result;
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
            $final_schema['@graph'] = [$schema,$branch_schema];
            update_post_meta($post_id, '_injected_script',  json_encode($final_schema));
            update_post_meta($post_id, '_injected_faq_script',  json_encode($faq_schema));
            $results[] = json_encode($final_schema);
        }
        wp_reset_postdata();

        wp_send_json_success([
            'schema' => $results,
        ]);
    }
}