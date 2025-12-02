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

$review_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'review_posttype'
    )
);

$acf_fields = array();
$taxonomy_fields = array();
if($review_post_type){
    $field_groups = acf_get_field_groups(array('post_type' => $review_post_type));

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
}

$all_fields = array_merge($builtin_fields, $acf_fields);

?>
<div style="display:flex; flex-direction:column; gap:16px;">
    <h2>Review</h2>
    <div>
        <label>Review body</label><br>
        <select name="review-body" id="schema-generator-review-body">
            <option value="" selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div>
        <label>Review rating</label><br>
        <select name="review-rating" id="schema-generator-review-rating">
            <option value="" selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>Author</label><br>
        <select name="review-author" id="schema-generator-review-author">
            <option value=""  selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    

    <div>
        <label>Date published</label><br>
        <select name="review-date-published" id="schema-generator-review-date-published">
            <option value="" selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
        
    <div style="margin-top:16px;">
        <button id="schema-generator-review-snippet-save-btn" class="button button-primary">Save</button>
    </div>
</div>


<script>
    jQuery(document).ready(function($) {
        hideLoading();
        $.ajax({
            url: schemaAjax.ajax_url, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'get_schema_by_page', // AJAX action name
                page: "review",                  // Page value to query
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
        $('#schema-generator-review-snippet-save-btn').on('click', function(e) {
            e.preventDefault();

            let page = 'review';
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