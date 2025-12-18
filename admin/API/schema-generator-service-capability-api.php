<?php
//generate schema for service capability pages
add_action('wp_ajax_service_capability_generate_schema', 'service_capability_generate_schema');

function service_capability_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';
    $schema=[];
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
    //get homepage properties
    $homepage_properties = [];
    $homepage_properties_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'home_page'
        )
    );
    foreach ($homepage_properties_rows as $row){
        $homepage_properties[ $row->property ] = $row->value;
    }
    //get service area properties
    $service_area_settings = [];
    $service_area_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'service-area'
        )
    );
    foreach ($service_area_rows as $row){
        $service_area_settings[ $row->property ] = $row->value;
    }

    $post_type =$global_settings['service_capability_posttype'];
    $post_taxo = $global_settings['service_capability_taxonomy'];
    $post_term = $global_settings['service_capability_term'];
    $service_capability_post_type = $global_settings['service_capability_posttype'];
    $service_capability_taxo = $global_settings['service_capability_taxonomy'];
    $service_capability_term = $global_settings['service_capability_term'];
    $service_area_slug = $global_settings['service_area_taxonomy_slug'];
    $service_slug = $global_settings['service_taxonomy_slug'];
    $single_address = $global_settings['single_location'];
    $service_area_post_type = $global_settings['service_area_posttype'];
    $service_area_taxo = $global_settings['service_area_taxonomy'];
    $service_area_term = $global_settings['service_area_term'];
    
    $service_area_name = $service_area_settings['service-area-name'];
    $service_area_street = $service_area_settings['service-area-street-address'];
    $service_area_city =  $service_area_settings['service-area-city'];
    $service_area_province =  $service_area_settings['service-area-province'];
    $service_area_postal =  $service_area_settings['service-area-postal-code'];
    $manual_service_capability_posts =  $global_settings['manual_service_capability_posts'];
    if(isset($manual_service_capability_posts)){
        $manual_service_capability_posts = json_decode(stripslashes($manual_service_capability_posts),true);
    }else{
        $manual_service_capability_posts = [];
    }


    //fetch all posts
    if($post_type!=""){
        $post_args = [
            'post_type'=> $post_type,
            'posts_per_page' => -1,//change this
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
            'service-capability'
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
            if($saved_settings['service-capability-name']){
                $field = explode(',', $saved_settings['service-capability-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['name'] = get_field($field_name);
                }
            }
            //description
            if($saved_settings['service-capability-description']){
                $field = explode(',', $saved_settings['service-capability-description']);
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
                if(isset($service_area_post_type)){
                    $service_area_terms = get_the_terms( $post_id, $service_area_slug );
                    $service_area_args = [
                        'post_type'      => $service_area_post_type,
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                    ];
                    $tax_query =  [
                        'relation' => 'AND',
                        [
                            'taxonomy' => $service_area_slug,
                            'field'    => 'id',
                            'terms'    => $service_area_terms[0]->term_id,
                        ],
                    ];
                    if($service_area_term && $service_area_taxo){
                        $tax_query[] = 
                        [
                            'taxonomy' => $service_area_taxo,
                            'field'    => 'id',
                            'terms'    => $service_area_term,
                        ];
                    }
                    $service_area_args['tax_query'] = $tax_query;
                }elseif(isset($manual_service_area_posts) && $manual_service_area_posts!=[]){
                    $service_area_args = [
                        'post_type' => 'any',
                        'post__in'       => $manual_service_area_posts,
                        'orderby'        => 'post__in',
                        'posts_per_page' => 1
                    ];
                    $service_area_terms = get_the_terms( $post_id, $service_area_slug );
                    $service_area_args['tax_query'] =  [
                        'relation' => 'AND',
                        [
                            'taxonomy' => $service_area_slug,
                            'field'    => 'id',
                            'terms'    => $service_area_terms[0]->term_id,
                        ],
                    ];
                    
                }else{
                    $service_area_args = [];
                }
                
                $service_area_query = new WP_Query($service_area_args);
                if ($service_area_query->have_posts()) {
                    $provider_schema = [];
                    $areaserved_schema = [];
                    while ($service_area_query->have_posts()) {
                        $service_area_query->the_post();  
                        $service_area_id = get_the_ID();
                        $service_area_url = get_post_permalink($service_area_id);
                        $service_area_areaserved_id = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT value FROM $table_name WHERE page = %s and property = %s",
                                'service-area',
                                'service-area-street-areaserved-id'
                            )
                        );
                        //provider
                        $provider_schema[] = ["@id" => $service_area_url . '#localbusiness',"url" => $service_area_url];
                        //areaServce
                        $single_areaserved = [];
                        $single_areaserved["@type"] = "City";
                        if( $service_area_city){
                            $field = explode(',', $service_area_city );
                            $field_name = $field[0];
                            $field_type = $field[1];
                            if ($field_type == 'built-in') {
                                $single_areaserved['name'] = get_post_field($field_name,$service_area_id);
                            } elseif ($field_type == 'ACF') {
                                $single_areaserved['name'] = get_field($field_name,$service_area_id);
                            }
                        }
                        if( $service_area_areaserved_id){
                            $field = explode(',', $service_area_areaserved_id );
                            $field_name = $field[0];
                            $field_type = $field[1];
                            if ($field_type == 'built-in') {
                                $single_areaserved['sameAs'] = get_post_field($field_name,$service_area_id);
                            } elseif ($field_type == 'ACF') {
                                $single_areaserved['sameAs'] = get_field($field_name,$service_area_id);
                            }
                        }
                        $areaserved_schema[] = $single_areaserved;
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
                    $schema['provider'] = $provider_schema;
                    $schema['areaServed'] = $areaserved_schema;
                }
            }else{
                //provider from home page if single location
                $schema['provider'] = ["@id" => home_url() . '/#localbusiness',"url" => home_url() , 'name' => $homepage_properties['name']];
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

            // Blog
            $service_terms = wp_get_post_terms($post_id, $service_slug, ['fields' => 'ids']);
            if (!empty($terms) && !is_wp_error($terms)) {
                $blog_args = [
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'tax_query'      => [
                        [
                            'taxonomy' => $service_slug,
                            'field'    => 'term_id',
                            'terms'    => $service_terms,
                        ],
                    ],
                ];
            }

            $blog_query = new WP_Query($blog_args);
            if ($blog_query->have_posts()) {
                $blog_schema = [];
                
                while ($blog_query->have_posts()) {
                    $blog_query->the_post();  
                    $blog_id = get_the_ID();
                    $single_blog = [];
                    $single_blog['@type'] = "WebPage";
                    $blog_url = get_permalink($blog_id);
                    $single_blog['@id'] = $blog_url . "#article";
                    $single_blog['url'] = $blog_url;
                    $blog_name = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'blog',
                            'headline'
                        )
                    );
                    if($blog_name){
                        $field = explode(',', $blog_name);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_blog['name'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_blog['name'] = get_field($field_name);
                        }
                    }
                    $blog_schema[] = $single_blog;
                }   
                wp_reset_postdata();
                $schema["isRelatedTo"] = $blog_schema;

            }

            // Past Project
            $service_terms = wp_get_post_terms($post_id, $service_slug, ['fields' => 'ids']);
            $past_project_post_type = $global_settings["past_project_posttype"];
            if (!empty($terms) && !is_wp_error($terms) && $past_project_post_type) {
                $past_project_args = [
                    'post_type'      => $past_project_post_type,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'tax_query'      => [
                        [
                            'taxonomy' => $service_slug,
                            'field'    => 'term_id',
                            'terms'    => $service_terms,
                        ],
                    ],
                ];
            }

            $past_project_query = new WP_Query($past_project_args);
            if ($past_project_query->have_posts()) {
                $past_project_schema = [];
                
                
                while ($past_project_query->have_posts()) {
                    $past_project_query->the_post();  
                    $project_id = get_the_ID();
                    $single_project = [];
                    $single_project['@type'] = "CreativeWork";
                    $project_url = get_permalink($project_id);
                    $single_project['@id'] = $project_url . "#project";
                    $single_project['url'] = $project_url;
                    $project_name = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'past-project',
                            'name'
                        )
                    );
                    if($project_name){
                        $field = explode(',', $project_name);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_project['name'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_project['name'] = get_field($field_name);
                        }
                    }
                    $past_project_schema[] = $single_project;
                }   
                wp_reset_postdata();
                $schema["subjectOf"] = $past_project_schema;

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

            //Product schema for testing
            $product_schema = [];
            $product_schema['@type'] = 'Product';
             //name
            if($saved_settings['service-general-name']){
                $field = explode(',', $saved_settings['service-general-name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $product_schema['name'] = get_post_field($field_name,$post_id);
                } elseif ($field_type == 'ACF') {
                    $product_schema['name'] = get_field($field_name,$post_id);
                }
            }
            //description
            if($saved_settings['service-general-description']){
                $field = explode(',', $saved_settings['service-general-description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $product_schema['description'] = get_post_field($field_name,$post_id);
                } elseif ($field_type == 'ACF') {
                    $product_schema['description'] = get_field($field_name,$post_id);
                }
            }
            //aggregate rating
            $aggregateRating_schema = get_aggregate_review();
            if(isset($aggregateRating_schema)){
                $product_schema['aggregateRating'] = $aggregateRating_schema;
            }


            $final_schema['@graph'] = [$schema,$branch_schema,$product_schema];
            update_post_meta($post_id, '_injected_script',  json_encode($final_schema));
            if($faq_schema['mainEntity']){
                update_post_meta($post_id, '_injected_faq_script',  json_encode($faq_schema));
            }else{
                update_post_meta($post_id, '_injected_faq_script',  "");
            }
            
            $results[] = json_encode($final_schema);
        }
        wp_reset_postdata();

        wp_send_json_success([
            'schema' => $results,
            'test'=>$blog_name
        ]);
    }
}