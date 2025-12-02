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

$employee_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'employee_posttype'
    )
);

$acf_fields = array();
$taxonomy_fields = array();
if($employee_post_type){
    $field_groups = acf_get_field_groups(array('post_type' => $employee_post_type));

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
    <h2>Employee</h2>
    <div>
        <label>Employee Name</label><br>
        <select name="employee-name" id="schema-generator-employee-name">
            <option value="" selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div>
        <label>Employee Job Title</label><br>
        <select name="employee-job-title" id="schema-generator-employee-job-title">
            <option value="" selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>Employee Description</label><br>
        <select name="employee-description" id="schema-generator-employee-description">
            <option value=""  selected>Select field name</option>
            <?php foreach($all_fields as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div>
        <label><b>Employee credential</b></label>

        <div style="margin-top:8px;">
            <label>Employee credential</label><br>
            <select id="schema-generator-employee-credential" name="employee-credential">
                <option value="">-- Select Repeater Field --</option>
            </select>
        </div>
        <div style="margin-top:8px;">
            <label>Competency Required Slug(Employee Credential)</label><br>
            <select name="employee-compenency-required" id="schema-generator-employee-compenency-required" disabled>
                <option value="">-- Select Sub Field --</option>
            </select>
        </div>

        <div style="margin-top:8px;">
            <label>Credential Category Slug(Employee Credential)</label><br>
            <select name="employee-credential-category" id="schema-generator-employee-credential-category" disabled>
                <option value="">-- Select Sub Field --</option>
            </select>
        </div>

        <div style="margin-top:8px;">
            <label>reconizedBy Slug(Employee Credential)</label><br>
            <select name="employee-reconizedby" id="schema-generator-employee-reconizedby" disabled>
                <option value="">-- Select Sub Field --</option>
            </select>
        </div>
    </div>
    <div>
        <label><b>Employee Certification</b></label>
        <div style="margin-top:8px;">
            <label>Employee Certification</label><br>
            <select id="schema-generator-employee-certification" name="employee-certification">
                <option value="">-- Select Repeater Field --</option>
            </select>
        </div>
        <div style="margin-top:8px;">
            <label>Certification Identification Slug(Employee Credential)</label><br>
            <select name="employee-certification-identification" id="schema-generator-employee-certification-identification" disabled>
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-top:8px;">
            <label>issuedBy Slug(Employee Credential)</label><br>
            <select name="employee-issuedby" id="schema-generator-employee-issuedby" disabled>
                <option value="" selected>Select field name</option>
                <?php foreach($all_fields as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <div>
        
    <div style="margin-top:16px;">
        <button id="schema-generator-employee-snippet-save-btn" class="button button-primary">Save</button>
    </div>
</div>


<script>
    jQuery(document).ready(function($) {
        var selected_credential;
        var selected_compenency_required;
        var selected_credential_category;
        var selected_reconizedby;
        var selected_certification;
        var selected_certification_identification;
        var selected_issuedby;
        $.ajax({
            url: schemaAjax.ajax_url, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'get_schema_by_page', // AJAX action name
                page: "employee",                  // Page value to query
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
                        if(element.property == 'employee-credential'){
                            selected_credential = element.value;
                            loadCredentialSubfields(element.value);
                        }
                        if(element.property == 'employee-compenency-required'){
                            selected_compenency_required = element.value;
                        }
                        if(element.property == 'employee-credential-category'){
                            selected_credential_category = element.value;
                        }
                        if(element.property == 'employee-reconizedby'){
                            selected_reconizedby = element.value;
                        }
                        if(element.property == 'employee-certification'){
                            selected_certification = element.value;
                            loadCertificationSubfields(element.value);
                        }
                        if(element.property == 'employee-certification-identification'){
                            console.log(element);
                            selected_certification_identification = element.value;
                        }
                        if(element.property == 'employee-issuedby'){
                            selected_issuedby = element.value;
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
        $('#schema-generator-employee-snippet-save-btn').on('click', function(e) {
            e.preventDefault();

            let page = 'employee';
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

        //get all repeater fields
        const postType = '<?echo $employee_post_type?>'; 
        $.post(ajaxurl, {
            action: 'get_acf_repeaters',
            post_type: postType
        }, function(response) {
            if (response.success) {
                const certification_options = response.data.map(f => f.name == selected_certification ? `<option value="${f.name}" selected>${f.label}</option>`:`<option value="${f.name}">${f.label}</option>`);
                const credential_options = response.data.map(f => f.name == selected_credential ? `<option value="${f.name}" selected>${f.label}</option>` : `<option value="${f.name}">${f.label}</option>`);
                $('#schema-generator-employee-credential').html('<option value="">-- Select Repeater Field --</option>' + credential_options.join(''));
                $('#schema-generator-employee-certification').html('<option value="">-- Select Repeater Field --</option>' + certification_options.join(''));
            } else {
                $('#schema-generator-employee-credential').html('<option>No repeater fields found</option>');
                $('#schema-generator-employee-certification').html('<option>No repeater fields found</option>');
            }
        });

        // when choose repeater,load subfields
        $('#schema-generator-employee-credential').on('change', function() {
            const repeaterName = $(this).val();
            loadCredentialSubfields(repeaterName);
        });
        $('#schema-generator-employee-certification').on('change', function() {
            const repeaterName = $(this).val();
            loadCertificationSubfields(repeaterName);
        });
        function loadCredentialSubfields(repeaterName){
            $('#schema-generator-employee-compenency-required').prop('disabled', true).html('<option>Loading...</option>');
            $('#schema-generator-employee-credential-category').prop('disabled', true).html('<option>Loading...</option>');
            $('#schema-generator-employee-reconizedby').prop('disabled', true).html('<option>Loading...</option>');

            $.post(ajaxurl, {
                action: 'get_acf_subfields',
                post_type: postType,
                repeater_name: repeaterName
            }, function(response) {
                if (response.success) {
                    const cr_options = response.data.map(f => f.name==selected_compenency_required ? `<option value="${f.name}" selected>${f.label}</option>` :`<option value="${f.name}">${f.label}</option>`);
                    const cc_options = response.data.map(f => f.name==selected_credential_category ? `<option value="${f.name}" selected>${f.label}</option>` : `<option value="${f.name}">${f.label}</option>`);
                    const rb_options = response.data.map(f => f.name==selected_reconizedby ? `<option value="${f.name}" selected>${f.label}</option>` : `<option value="${f.name}">${f.label}</option>`);
                    $('#schema-generator-employee-compenency-required').html('<option value="">-- Select Sub Field --</option>' + cr_options.join('')).prop('disabled', false);
                    $('#schema-generator-employee-credential-category').html('<option value="">-- Select Sub Field --</option>' + cc_options.join('')).prop('disabled', false);
                    $('#schema-generator-employee-reconizedby').html('<option value="">-- Select Sub Field --</option>' + rb_options.join('')).prop('disabled', false);
                } else {
                    $('#schema-generator-employee-compenency-required').html('<option>No subfields found</option>');
                    $('#schema-generator-employee-credential-category').html('<option>No subfields found</option>');
                    $('#schema-generator-employee-reconizedby').html('<option>No subfields found</option>');
                }
            });
        }
        function loadCertificationSubfields(repeaterName){
            $('#schema-generator-employee-certification-identification').prop('disabled', true).html('<option>Loading...</option>');
            $('#schema-generator-employee-issuedby').prop('disabled', true).html('<option>Loading...</option>');

            $.post(ajaxurl, {
                action: 'get_acf_subfields',
                post_type: postType,
                repeater_name: repeaterName
            }, function(response) {
                if (response.success) {
                    const ci_options = response.data.map(f => f.name==selected_certification_identification ? `<option value="${f.name}" selected>${f.label}</option>`: `<option value="${f.name}">${f.label}</option>`);
                    const ib_options = response.data.map(f => f.name==selected_issuedby ? `<option value="${f.name}" selected>${f.label}</option>` : `<option value="${f.name}">${f.label}</option>`);
                    $('#schema-generator-employee-certification-identification').html('<option value="">-- Select Sub Field --</option>' + ci_options.join('')).prop('disabled', false);
                    $('#schema-generator-employee-issuedby').html('<option value="">-- Select Sub Field --</option>' + ib_options.join('')).prop('disabled', false);
                } else {
                    $('#schema-generator-employee-certification-identification').html('<option>No subfields found</option>');
                    $('#schema-generator-employee-issuedby').html('<option>No subfields found</option>');
                }
            });
        }
    });
</script>