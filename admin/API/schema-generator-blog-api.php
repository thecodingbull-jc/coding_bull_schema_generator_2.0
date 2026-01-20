<?php
//generate schema for service general pages
add_action('wp_ajax_blog_generate_schema', 'blog_generate_schema');

function blog_generate_schema(){
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

    //single address
    $single_location = $global_settings['single_location'];

    //home page properties
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

    //home name
    $home_name = $home_settings["name"];

    //fetch blog setting
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'blog'
        ),
        ARRAY_A
    );

    $saved_settings = [];
    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            $saved_settings[ $row['property'] ] = $row['value'];
        }
    }

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // fetch all
    ];

    $query = new WP_Query($args);


    //loop through posts and generate schema for each post
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();  
            $post_id = get_the_ID();
            $schema = [];
            $schema['@context'] = "https://schema.org";
            $blog_schema = [];

            $blog_schema["@type"] = "Article";

            //id and url
            $url = get_permalink($post_id);
            $blog_schema["@id"] = $url . "#article";
            $blog_schema["url"] = $url;

            //headline and description
            if($saved_settings['headline']){
                $field = explode(',', $saved_settings['headline']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $blog_schema['headline'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $blog_schema['headline'] = get_field($field_name);
                }
            }
            if($saved_settings['description']){
                $field = explode(',', $saved_settings['description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $blog_schema['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $blog_schema['description'] = get_field($field_name);
                }
            }
            if($saved_settings['article-selection']){
                $terms = get_the_terms( $post_id, $saved_settings['article-selection'] );
                if(!empty($terms) && !is_wp_error($terms)){
                    $terms_arr = wp_list_pluck($terms, 'name');;
                    if(count($terms_arr)===1){
                        $blog_schema['articleSection'] = $terms_arr[0];
                    }else{
                        $blog_schema['articleSection'] = $terms_arr;
                    }
                }
            }
            //author and publisher
            $author = [];
            $author['@type'] = "Organization";
            $author['@id'] = home_url('/') . '#localbusiness';
            $author['url'] = home_url('/');
            $author['name'] = $home_name;
            $blog_schema['author'] = $author;
            $blog_schema['publisher'] = $author;

            //publish date
            if($saved_settings['publish-date']){
                $field = explode(',', $saved_settings['publish-date']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $date = get_post_field($field_name);
                    if($date){
                        $time_stamp = strtotime($date);
                        $blog_schema["datePublished"] = date("Y-m-d", $time_stamp);
                    }
                } elseif ($field_type == 'ACF') {
                    $date = get_field($field_name);
                    if($date){
                        $time_stamp = strtotime($date);
                        $blog_schema["datePublished"] = date("Y-m-d", $time_stamp);
                    }
                }
            }

            
            //Fetch services with same service taxonomy
            $service_general_post_type = $global_settings['service_general_posttype'];
            $service_general_taxonomy = $global_settings['service_general_taxonomy'];
            $service_general_term = $global_settings['service_general_term'];
            $service_capability_taxonomy = $global_settings['service_capability_taxonomy'];
            $service_capability_term = $global_settings['service_capability_term'];
            $service_taxonomy = $global_settings['service_taxonomy_slug'];
            $current_terms = wp_get_post_terms( $post_id, $service_taxonomy, array( 'fields' => 'ids' ) );

            $args = array(
                'post_type' => $service_general_post_type,
                'posts_per_page' => -1,
                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'OR',
                        array(
                            'taxonomy' => $service_general_taxonomy,
                            'field'    => 'term_id', 
                            'terms'    => $service_general_term,
                        ),
                        array(
                            'taxonomy' => $service_capability_taxonomy,
                            'field'    => 'term_id', 
                            'terms'    => $service_capability_term,
                        ),
                    ),
                    array(
                        'taxonomy' => $service_taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $current_terms,
                    ),
                ),
            );

            $service_query = new WP_Query( $args );
            if ( $service_query->have_posts() ) {
                $service_schema = [];
                while ( $service_query->have_posts() ) {
                    $single_service = [];
                    $single_service['@type'] = "Service";
                    $service_query->the_post();
                    $review_id = get_the_ID();
                    $service_name = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'service-general',
                            'service-general-name'
                        )
                    );
                    if($service_name){
                        $field = explode(',', $service_name);
                        $field_name = $field[0];
                        $field_type = $field[1];
                        if ($field_type == 'built-in') {
                            $single_service['name'] = get_post_field($field_name);
                        } elseif ($field_type == 'ACF') {
                            $single_service['name'] = get_field($field_name);
                        }
                        $service_url = get_permalink($service_id);
                        $single_service['@id'] = $service_url . "#service";
                        $single_service['url'] = $service_url;
                    }
                    $service_schema[] = $single_service;
                }   
                wp_reset_postdata();
                $blog_schema["mentions"] = $service_schema;
            }

            //Branch schema
            $organization_schema = [];
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
                $organization_schema["@type"] = $home_businessType_text;
            }elseif($home_businessType){
                $organization_schema["@type"] = $home_businessType;
            }
            $field = explode(',', $home_name);
            $field_name = $field[0];
            $field_type = $field[1];
            
            $organization_schema['name'] = $home_name;
            $organization_schema["@id"] =  home_url('/') . '#localbusiness' ;
            $organization_schema["url"] =  home_url('/');

            //address
            if($single_location){
                $address_schema = [];
                $home_settings['addressLocality'] && $address_schema['addressLocality'] = $home_settings['addressLocality'];
                $home_settings['addressRegion'] && $address_schema['addressRegion'] = $home_settings['addressRegion'];
                $home_settings['addressCountry'] && $address_schema['addressCountry'] = $home_settings['addressCountry'];
                $home_settings['postalCode'] && $address_schema['postalCode'] = $home_settings['postalCode'];
                if($home_settings['hasStreetAddress']){
                    $address_schema['streetAddress'] = $home_settings['streetAddress'];
                    // $amanity_features= explode(',',$home_settings['amenityFeature']);
                    // $amanity_schema =[];
                    // foreach($amanity_features as $feature){
                    //     $amanity_schema[] = ["@type"=>"LocationFeatureSpecification", "name"=>$feature];
                    // }
                    // $schema['amenityFeature'] = $amanity_schema;
                }
                
                $organization_schema['address'] = $address_schema;
            }else{
                    //get service area properties
                    $service_area_properties = [];
                    $service_area_properties_rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT property, value FROM $table_name WHERE page = %s",
                            'service-area'
                        )
                    );
                    foreach ($service_area_properties_rows as $row){
                        $service_area_properties[ $row->property ] = $row->value;
                    
                    }
                    //service area post type taxo and term
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
                    $service_area_args = [];
                    if(isset($service_area_post_type)){
                        $service_area_args = [
                            'post_type'      => $service_area_post_type,
                            'posts_per_page' => -1,
                            'post_status'    => 'publish',
                        ];
                        if(isset($service_area_taxo ) && isset($service_area_term )){
                            $service_area_args['tax_query'] = [
                                'relation' => 'OR',
                                [
                                    'taxonomy' => $service_area_taxo,
                                    'field'    => 'id',
                                    'terms'    => $service_area_term
                                ]
                            ];
                        }
                    }
                    elseif((isset($manual_service_area_posts) && $manual_service_area_posts!=[])){
                        $service_area_args = [
                            'post_type' => 'any',
                            'post__in'       => $manual_service_area_posts,
                            'orderby'        => 'post__in',
                            'posts_per_page' => -1
                        ];
                    }
                    else{
                        $service_area_args = [];
                    }
                
                    $service_area_query = new WP_Query($service_area_args);
                    $address_schema = get_address_list($service_area_query,$service_area_properties['service-area-street-address'],$service_area_properties['service-area-city'],$service_area_properties['service-area-province'],$service_area_properties['service-area-country'], $service_area_properties['service-area-postal-code']);
                    if($address_schema){
                        $organization_schema["address"] = $address_schema;
                    }
            }
                        
            $schema["@graph"] = [$blog_schema,$organization_schema];
            update_post_meta($post_id, '_injected_script',  json_encode($schema));
            $results[] = json_encode($schema);
        }
        wp_reset_postdata();
        wp_send_json_success([
            'schema' => $results,
            'test' => $service_area_properties,
        ]);

    }
    
   
}