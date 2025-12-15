<?php
//generate schema for service general pages
add_action('wp_ajax_past_project_generate_schema', 'past_project_generate_schema');

function past_project_generate_schema(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema';

    // //fetch global setting
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

    // //home name
    // $home_name = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'home_page',
    //         'name'
    //     )
    // );
    

    // //fetch blog setting
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT property, value FROM $table_name WHERE page = %s",
            'past-project'
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
        'post_type'      => $global_settings['past_project_posttype'],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ];

    $query = new WP_Query($args);

    // //loop through posts and generate schema for each post
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();  
            $post_id = get_the_ID();
            $url = get_permalink($post_id);
            $schema = [];
            $schema['@context'] = "https://schema.org";
            $schema['@type'] = "CreativeWork";
            $schema['@id'] = $url . "#project";
            $schema['url'] = $url;
            if($saved_settings['name']){
                $field = explode(',', $saved_settings['name']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['name'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['name'] = get_field($field_name);
                }
            }
            if($saved_settings['description']){
                $field = explode(',', $saved_settings['description']);
                $field_name = $field[0];
                $field_type = $field[1];
                if ($field_type == 'built-in') {
                    $schema['description'] = get_post_field($field_name);
                } elseif ($field_type == 'ACF') {
                    $schema['description'] = get_field($field_name);
                }
            }

            //Fetch services with same service taxonomy and service area (about)
            $service_general_post_type = $global_settings['service_general_posttype'];
            $service_general_taxonomy = $global_settings['service_general_taxonomy'];
            $service_general_term = $global_settings['service_general_term'];
            $service_capability_taxonomy = $global_settings['service_capability_taxonomy'];
            $service_capability_term = $global_settings['service_capability_term'];
            $service_taxonomy = $global_settings['service_taxonomy_slug'];
            $service_area_taxonomy_slug = $global_settings['service_area_taxonomy_slug'];
            $service_terms = wp_get_post_terms( $post_id, $service_taxonomy, array( 'fields' => 'ids' ) );
            $service_area_terms = wp_get_post_terms( $post_id, $service_area_taxonomy_slug, array( 'fields' => 'ids' ) );

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
                        'terms'    => $service_terms,
                    ),
                    array(
                        'taxonomy' => $service_area_taxonomy_slug,
                        'field'    => 'term_id',
                        'terms'    => $service_area_terms,
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
                    $service_url = get_permalink($service_id);
                    $single_service['@id'] = $service_url . "#service";
                    $single_service['url'] = $service_url;
                    $service_schema[] = $single_service;
                }   
                wp_reset_postdata();
                $schema["about"] = $service_schema;
            }
            //Fetch service area information(creator)
            $service_area_post_type = $global_settings['service_area_posttype'];
            $service_area_taxonomy = $global_settings['service_area_taxonomy'];
            $service_area_term = $global_settings['service_area_term'];
            $tax_query = array(
                'relation' => 'AND',
                array(
                    'taxonomy' => $service_area_taxonomy_slug,
                    'field'    => 'term_id',
                    'terms'    => $service_area_terms,
                ),
            );
            if(isset($service_area_taxonomy ) && isset($service_area_term )){
                $tax_query[] = array(
                    'taxonomy' => $service_area_taxonomy,
                    'field'    => 'term_id', 
                    'terms'    => $service_area_term,
                );
            }
            $service_area_args = array(
                'post_type' => $service_area_post_type,
                'posts_per_page' => -1,
                'tax_query' => $tax_query
            );

            $service_area_query = new WP_Query( $service_area_args );
            if ($service_area_query->have_posts()) {
                $service_area_schema = [];
                while ($service_area_query->have_posts()) {
                    $service_area_query->the_post();  
                    $service_area_id = get_the_ID();
                    $single_service_area = [];
                    $home_businessType_text = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT value FROM $table_name WHERE page = %s and property = %s",
                            'home_page',
                            'businessType-text'
                        )
                    );
                    if($home_businessType_text){
                        $single_service_area["@type"] = $home_businessType_text;
                    }elseif($home_businessType){
                        $single_service_area["@type"] = $home_businessType;
                    }
                    
                    $url = get_permalink($service_area_id);
                    $single_service_area['@id'] = $url . "#localbusiness";
                    $single_service_area['url'] = $url;

                    $service_area_schema[] = $single_service_area;
                }
                $schema["creator"] = $service_area_schema;
            }
            update_post_meta($post_id, '_injected_script',  json_encode($schema));
            $results[] = json_encode($schema);
        }
    //     wp_reset_postdata();
        wp_send_json_success([
            'schema' => $results,
            'testing'=> $service_area_args,
            'testing2'=> $service_area_query
        ]);

    }
    
   
}