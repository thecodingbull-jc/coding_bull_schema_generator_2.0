<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$schema_table_name = $wpdb->prefix . 'tcb_schema'; 

$service_post_type = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
        'global',
        'service_general_posttype'
    )
);

?>
<div style="display:flex; flex-direction:column; gap:16px;">
    <h2>FAQ</h2>

    <div>
        <div style="margin-top:8px;">
            <label>FAQ</label><br>
            <select id="schema-generator-faq" name="faq">
                <option value="">-- Select Repeater Field --</option>
            </select>
        </div>
        <div style="margin-top:8px;">
            <label>Question</label><br>
            <select name="faq-question" id="schema-generator-faq-question" disabled>
                <option value="">-- Select Sub Field --</option>
            </select>
        </div>

        <div style="margin-top:8px;">
            <label>Answer</label><br>
            <select name="faq-answer" id="schema-generator-faq-answer" disabled>
                <option value="">-- Select Sub Field --</option>
            </select>
        </div>

    </div>
        
    <div style="margin-top:16px;">
        <button id="schema-generator-faq-snippet-save-btn" class="button button-primary">Save</button>
    </div>
</div>


<script>
    jQuery(document).ready(function($) {
        var selected_repeater;
        var selected_question;
        var selected_answer;
        $.ajax({
            url: schemaAjax.ajax_url, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'get_schema_by_page', // AJAX action name
                page: "faq",                  // Page value to query
                nonce: schemaAjax.nonce // Security nonce
            },
            success: function(response) {
                if(response.success){
                    //console.log(response.data);
                    response.data.properties.forEach(element => {
                        //console.log(element.property);
                        $(`input[name=${element.property}]`).val(element.value);
                        $(`select[name=${element.property}]`).val(element.value);
                        $(`textarea[name=${element.property}]`).val(element.value);
                        if(element.value=='1'){
                            $(`input[name=${element.property}]`).prop('checked', true);
                        }
                        else if(element.value=='0'){
                            $(`input[name=${element.property}]`).prop('checked', false);
                        }
                        if(element.property == 'faq'){
                            selected_repeater = element.value;
                            loadFAQSubfields(element.value);
                        }
                        if(element.property == 'faq-question'){
                            selected_question = element.value;
                        }
                        if(element.property == 'faq-answer'){
                            selected_answer = element.value;
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
        $('#schema-generator-faq-snippet-save-btn').on('click', function(e) {
            e.preventDefault();

            let page = 'faq';
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
        const postType = '<?echo $service_post_type?>'; 
        $.post(ajaxurl, {
            action: 'get_acf_repeaters',
            post_type: postType
        }, function(response) {
            if (response.success) {
                //console.log(selected_repeater);
                const options = response.data.map(f => f.name == selected_repeater ? `<option value="${f.name}" selected>${f.label}</option>` : `<option value="${f.name}">${f.label}</option>`);
                
                
                $('#schema-generator-faq').html('<option value="">-- Select Repeater Field --</option>' + options.join(''));
            } else {
                $('#schema-generator-faq').html('<option>No repeater fields found</option>');
            }
        });

        // when choose repeater,load subfields
       $('#schema-generator-faq').on('change', function() {
            const repeaterName = $(this).val();
            loadFAQSubfields(repeaterName);
        });
        function loadFAQSubfields(repeaterName) {
            $('#schema-generator-faq-question').prop('disabled', true).html('<option>Loading...</option>');
            $('#schema-generator-faq-answer').prop('disabled', true).html('<option>Loading...</option>');

            $.post(ajaxurl, {
                action: 'get_acf_subfields',
                post_type: postType,
                repeater_name: repeaterName
            }, function(response) {
                if (response.success) {
                    //console.log(selected_question);
                    //console.log(selected_answer);
                    const question_options = response.data.map(f => f.name == selected_question?`<option value="${f.name}" selected>${f.label}</option>`:`<option value="${f.name}">${f.label}</option>`);
                    const answer_options = response.data.map(f => f.name == selected_answer?`<option value="${f.name}" selected>${f.label}</option>`:`<option value="${f.name}">${f.label}</option>`);
                    $('#schema-generator-faq-question')
                        .html('<option value="">-- Select Sub Field --</option>' + question_options.join(''))
                        .prop('disabled', false);
                    $('#schema-generator-faq-answer')
                        .html('<option value="">-- Select Sub Field --</option>' + answer_options.join(''))
                        .prop('disabled', false);
                } else {
                    $('#schema-generator-faq-question').html('<option>No subfields found</option>');
                    $('#schema-generator-faq-answer').html('<option>No subfields found</option>');
                }
            });
        }
    });
</script>