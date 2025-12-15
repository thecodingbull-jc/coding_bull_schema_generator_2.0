<?php

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
    if($homepage_properties['businessType-text']){
        $schema['@type']=$homepage_properties['businessType-text'];
    }elseif($homepage_properties['businessType']){
        $schema['@type']=$homepage_properties['businessType'];
    }
    //URL and ID
    $home_url = home_url();
    $schema['@id']=$home_url . '/#localbusiness';
    $schema['url']=$home_url;
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
    //sameAs(social media)
    if($homepage_properties['social-media']){
        $schema['sameAs']=explode(',',$homepage_properties['social-media']);
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
    // $areaServed_schema = [];
    // $service_post_type = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'service_general_posttype'
    //     )
    // );
    // $general_service_taxo = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'service_general_taxonomy'
    //     )
    // );
    // $general_service_term = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'service_general_term'
    //     )
    // );
    // $capability_service_taxo = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'service_capability_taxonomy'
    //     )
    // );
    // $capability_service_term = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'service_capability_term'
    //     )
    // );
    // $manual_service_general_posts = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'manual_service_general_posts'
    //     )
    // );
    // $manual_service_capability_posts = $wpdb->get_var(
    //     $wpdb->prepare(
    //         "SELECT value FROM $table_name WHERE page = %s and property = %s",
    //         'global',
    //         'manual_service_capability_posts'
    //     )
    // );
    // if(isset($manual_service_general_posts)){
    //     $manual_service_general_posts = json_decode(stripslashes($manual_service_general_posts),true);
    // }else{
    //     $manual_service_general_posts = [];
    // }
    // if(isset($manual_service_capability_posts)){
    //     $manual_service_capability_posts = json_decode(stripslashes($manual_service_capability_posts),true);
    // }else{
    //     $manual_service_capability_posts = [];
    // }
    // $service_result = [];
    // $service_args = [];
    // if(isset($service_post_type)){
    //     $service_args = [
    //         'post_type'      => $service_post_type,
    //         'posts_per_page' => -1,
    //         'post_status'    => 'publish',
    //     ];

    //     $service_args['tax_query'] = [
    //         'relation' => 'OR',
    //         [
    //             'taxonomy' => $general_service_taxo,
    //             'field'    => 'id',
    //             'terms'    => $general_service_term
    //         ],
    //         [
    //             'taxonomy' => $capability_service_taxo,
    //             'field'    => 'id',
    //             'terms'    => $capability_service_term
    //         ],
    //     ];
    // }
    // elseif((isset($manual_service_general_posts) && $manual_service_general_posts!=[]) || (isset($manual_service_capability_posts) && $manual_service_capability_posts!=[])){
    //     $manual_service_merge =  array_merge($manual_service_general_posts,$manual_service_capability_posts);
    //     $service_args = [
    //         'post_type' => 'any',
    //         'post__in'       => $manual_service_merge,
    //         'orderby'        => 'post__in',
    //         'posts_per_page' => -1
    //     ];
    // }
    // else{
    //     $service_args = [];
    // }

    // $service_query = new WP_Query($service_args);
    // if ($service_query->have_posts()) {
    //     while ($service_query->have_posts()) {
    //         $service_query->the_post();
    //         $single_service = [];
    //         if($homepage_properties['hasOfferCatalog-name']){
    //             $field = explode(',', $homepage_properties['hasOfferCatalog-name']);
    //             $field_name = $field[0];
    //             $field_type = $field[1];
    //             if ($field_type == 'built-in') {
    //                 $single_service['name'] = get_post_field($field_name);
    //             } elseif ($field_type == 'ACF') {
    //                 $single_service['name'] = get_field($field_name);
    //             }
    //         }
    //         if($homepage_properties['hasOfferCatalog-description']){
    //             $field = explode(',', $homepage_properties['hasOfferCatalog-description']);
    //             $field_name = $field[0];
    //             $field_type = $field[1];
    //             if ($field_type == 'built-in') {
    //                 $single_service['description'] = get_post_field($field_name);
    //             } elseif ($field_type == 'ACF') {
    //                 $single_service['description'] = get_field($field_name);
    //             }
    //         }
    //         $single_service['url'] = get_post_permalink(get_the_ID());
    //         $service_result[] = $single_service;
    //     }
    //     $schema["hasOfferCatalog"] = $service_result;
    // }
    // wp_reset_postdata();
    //areaServed
    if(!$single_location){
        $areaServed_schema = [];
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
        if(isset($service_post_type)){
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
        $areaServed = [];
        $areaServedSchema = [];
        if ($service_area_query->have_posts()) {
            while ($service_area_query->have_posts()) {
                $single_areaServed = [];
                $single_areaServed['@type'] = "City" ;
                $service_area_query->the_post();
                $city_field = explode(',', $homepage_properties['areaServed-city']);
                $city_field_name = $city_field[0];
                $city_field_type = $city_field[1];
                $id_field = explode(',', $homepage_properties['areaServed-id']);
                $id_field_name = $id_field[0];
                $id_field_type = $id_field[1];

                if ($city_field_type == 'built-in') {
                    if( !in_array(get_post_field($city_field_name), $areaServed)){
                        $single_areaServed['name'] = get_post_field($city_field_name);
                        $areaServed[] =get_post_field($city_field_name);
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
                        if ($id_field_type == 'built-in') {
                            $single_areaServed['sameAs'] = get_post_field($id_field_name)??"";
                        } elseif ($id_field_type == 'ACF') {
                            $single_areaServed['sameAs'] = get_field($id_field_name)??"";
                        }
                    }
                }
                if($single_areaServed !== ["@type"=>"City"]){
                    $areaServedSchema[] = $single_areaServed;
                }
            }
            $schema["areaServed"] = $areaServedSchema;
        }
        wp_reset_postdata();
    }
    //address
    if(!$single_location){
        $address_schema = get_address_list($service_area_query,$homepage_properties['address-street'],$homepage_properties['address-city'],$homepage_properties['address-province'], $homepage_properties['address-postal']);
        if($address_schema){
            $schema["address"] = $address_schema;
        }
    }
    //Reviews
    $aggregateRating_schema = [];
    $aggregateRating_schema['@type'] = "AggregateRating";
    //$total_rating = 0;
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
        // if ($review_query->have_posts()) {
        //     while ($review_query->have_posts()) {
        //         $review_query->the_post();
        //          if($review_settings['review-rating']){
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
        $aggregateRating_schema['ratingValue'] = 5;
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
        'testing'=> $service_area_query
    ]);
}