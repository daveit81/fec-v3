<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
$userid = JFactory::getUser()->id;
$authuser = '42';
?>
<h1><?php echo $this->message; ?></h1>
<script>
jQuery(document).ready(function() {

$('#bonusform').find('[name="infos"]')
            .each(function() {
                $(this)
                    .ckeditor( function() { /* callback code */ }, { toolbar:
      [
         ['Source', '-', 'Bold', 'Italic', '-', 'NumberedList', 'BulletedList'],
         ['UIColor']
      ] })
                        
            });

$('#datetimepicker1, #datetimepicker2').datetimepicker({
                icons: {
                    time: "fa fa-clock-o",
                    date: "fa fa-calendar",
                    up: "fa fa-arrow-up",
                    down: "fa fa-arrow-down"
                },
                
                format: 'MM-DD-YYYY hh:mm A'
            });

    $('#bonusform')
    
    .formValidation({
        framework: 'bootstrap',
        icon: {
            valid: 'fa fa-check',
            invalid: 'fa fa-times',
            validating: 'fa fa-refresh'
        },
        fields: {
            casino: {
            onSuccess: function(e, data) {
                        $('#bonusform').find("#step-1").removeClass("hide");
                    },
            onError: function(e, data) {
                        $('#bonusform').find("#step-1").addClass("hide");
                    },
                
                validators: {                      
                        notEmpty: {
                            message: 'Please select a Casino.'
                        }                 
                   
            }
            },
            
             type: {
            onSuccess: function(e, data) {
                        $('#bonusform').find("#step-2").removeClass("hide");
                        
                    },
            onError: function(e, data) {
                        $('#bonusform').find("#step-2").addClass("hide");
                    },
                
                validators: {                      
                        notEmpty: {
                            message: 'Please select a Bonus Type.'
                        }                 
                   
            }
            },
            
            
             bonus: {
             
             validators: {                      
                        notEmpty: {
                            message: 'Please enter a value.'
                        },
                        numeric: {
                            message: 'The value is not a number'
                        }        
                   
            }
            
            },
            
            mincash: {
             
             validators: {                      
                       numeric: {
                            message: 'The value is not a number'
                        }        
                   
            }
            
            },
            
            maxcash: {
             
             validators: {                      
                       numeric: {
                            message: 'The value is not a number'
                        }        
                   
            }
            
            },
            
            maxbonus: {
             
             validators: {                      
                       numeric: {
                            message: 'The value is not a number'
                        }        
                   
            }
            
            },
            
            wr: {
             
             validators: {                      
                        notEmpty: {
                            message: 'Please enter a value.'
                        },
                        numeric: {
                            message: 'The value is not a number'
                        }        
                   
            }
            
            },
            
            fptime: {
             enabled: false,
             validators: {                      
                        
                        numeric: {
                            message: 'The value is not a number'
                        },
                        
                        notEmpty: {
                            message: 'Please, insert value'
                            }
                                
                   
            }
            
            },
            
            fptimeden: {
             enabled: false,
             validators: {                      
                        
                        notEmpty: {
                            message: 'Please, choose an option'
                            }
                                
                   
            }
            
            },
            
            
            cashable: {
                    validators: {
                        notEmpty: {
                            message: 'The cashable option is required'
                        }
                    }
                },
                
            exclusive: {
                    validators: {
                        notEmpty: {
                            message: 'Please select "Yes" for exclusive, "No" for regular promotion'
                        }
                    }
                },
                
            start: {
                validators: {
                   
                    date: {
                        message: 'The start date is not valid',
                        format: 'MM-DD-YYYY hh:mm A',
                        max: 'end'
                    }
                },
                onSuccess: function(e, data) {
                    // data.fv is the plugin instance
                    // Revalidate the end date if it's not valid
                    if (!data.fv.isValidField('end')) {
                        data.fv.revalidateField('end');
                    }
                }
            },
            
            end: {
                validators: {
                     date: {
                        message: 'The end date is not valid',
                        format: 'MM-DD-YYYY hh:mm A',
                        min: 'start'
                    }
                },
                onSuccess: function(e, data) {
                    // data.fv is the plugin instance
                    // Revalidate the start date if it's not valid
                    if (!data.fv.isValidField('start')) {
                        data.fv.revalidateField('start');
                    }
                }
            },
            'countriesok[]': {
                enabled: false,
                validators: {
                        callback: {
                            message: 'Please choose 2-3 browsers you use for developing',
                            callback: function(value, validator, $field) {
                                // Get the selected options
                                var optionsok = validator.getFieldElements('countriesok[]').val();
                                var optionsno = validator.getFieldElements('countriesno[]').val();
                              
                                var common = $.grep(value, function(element) {
    return ($.inArray(element, optionsno ) !== -1)
});

if (common == '') {

var error = 0;
} else {
var error = 1;
}

return (error == 0 && optionsok !== null);
                                
                                
                              //return (optionsok != null && $(optionsok).not(optionsno).length !== 0 && $(optionsno).not(optionsok).length !== 0);
                                
                               
                                
                                
                               
                            }
                        } 
                    }
            },
            
            'countriesno[]': {
                enabled: false,
                   validators: {
                        callback: {
                            message: 'Please choose 2-3 browsers you use for developing',
                             callback: function(value, validator, $field) {
                                // Get the selected options
                                var optionsno = validator.getFieldElements('countriesno[]').val();
                                var optionsok = validator.getFieldElements('countriesok[]').val();
                                //console.log(optionsok);
                                //console.log(optionsno);
                               
                            var common = $.grep(value, function(element) {
    return ($.inArray(element, optionsok ) !== -1)
});

if (common == '') {

var error = 0;
} else {
var error = 1;
}


                  return (error == 0 && optionsno !== null); 
                                 
                                //return (optionsno != null && $(optionsno).not(optionsok).length !== 0 && $(optionsok).not(optionsno).length !== 0);
                                
                            }
                        }
                    }
            },
            
            wrmethod: {
                    enabled: false,
                    validators: {
                        notEmpty: {
                            message: 'Please, select WR method'
                        }
                    }
                }
            
            
            
                    }
                    
    }).on('change', '[name="type"]', function() {
    
    
            switch ($(this).val()) {
                case 'nd':
                
                $('#percent').addClass('hide');
                $('#curr-val').removeClass('hide');
                $('#maxbonus').addClass('hide');
                //$('#wr-val').text('X Bonus');
                $('#wrmeth').addClass('hide');
                $('#fptime').addClass('hide');
                $('#fptimeden').addClass('hide');
                $('#mindep,#maxdep').addClass('hide');
                $('#bonusform').formValidation('enableFieldValidators', 'fptime', false);
                $('#bonusform').formValidation('enableFieldValidators', 'fptimeden', false);
                $('#bonusform').formValidation('enableFieldValidators', 'wrmethod', false);
                //$('#currency').removeClass('hide');
                    
                    break;
                    
                case 'fs':
                
                $('#percent').addClass('hide');
                $('#curr-val').addClass('hide');
                $('#maxbonus').addClass('hide');
                //$('#wr-val').text('X Bonus');
                $('#wrmeth').addClass('hide');
                $('#fptime').addClass('hide');
                $('#fptime').addClass('hide');
                $('#mindep,#maxdep').removeClass('hide');
                $('#bonusform').formValidation('enableFieldValidators', 'fptime', false);
                $('#bonusform').formValidation('enableFieldValidators', 'fptimeden', false);
                $('#bonusform').formValidation('enableFieldValidators', 'wrmethod', false);
                //$('#currency').addClass('hide');
                    
                    break;
                
                case 'fp':
                
                $('#percent').addClass('hide');
                $('#curr-val').addClass('hide');
                $('#maxbonus').addClass('hide');
                //$('#wr-val').text('X Bonus');
                $('#wrmeth').addClass('hide');
                $('#fptime').removeClass('hide');
                $('#fptime').removeClass('hide');
                $('#mindep,#maxdep').addClass('hide');
                $('#bonusform').formValidation('enableFieldValidators', 'fptime');
                $('#bonusform').formValidation('enableFieldValidators', 'fptimeden');
                $('#bonusform').formValidation('enableFieldValidators', 'wrmethod', false);
                
                    
                break;
                    
                default:
                $('#maxbonus').removeClass('hide');
                $('#percent').removeClass('hide');
                $('#curr-val').addClass('hide');
                //$('#wr-val').text('X Bonus + Deposit');
                $('#wrmeth').removeClass('hide');
                $('#fptime').addClass('hide');
                $('#fptime').addClass('hide');
                $('#mindep,#maxdep').removeClass('hide');
                $('#bonusform').formValidation('enableFieldValidators', 'fptime', false);
                $('#bonusform').formValidation('enableFieldValidators', 'fptimeden', false);
                $('#bonusform').formValidation('enableFieldValidators', 'wrmethod');
                //$('#currency').removeClass('hide');
                                                
                    break;
            }
            }).find('input[name="exclusive"], input[name="cashable"], input[name="wrmethod"]')
            // Init icheck elements
            .icheck({
                // The tap option is only available in v2.0
                tap: true,
                checkboxClass: 'icheckbox_square-red',
                radioClass: 'iradio_square-red'
            })
            // Called when the radios/checkboxes are changed
            .on('ifChanged', function(e) {
                // Get the field name
                var field = $(this).attr('name');
                $('#bonusform').formValidation('revalidateField', field);
                        
                
            })
            .end()
            .find('[name="countriesok[]"],[name="countriesno[]"]')
            .multiselect({
                enableFiltering: true,
                filterBehavior: 'text',
                includeSelectAllOption: false,
                onDropdownShown: function(e) {
                    adjustByScrollHeight();
                },
                onDropdownHidden: function(e) {
                    adjustByHeight();
                },
                
                onChange: function() {
        //console.log($('[name="countriesok[]"],[name="countriesno[]"]').val());
         var optionsok = $('[name="countriesok[]"]').val();
         var optionsno = $('[name="countriesno[]"]').val();
         if(optionsok !== null && optionsno !== null) {
         $('#bonusform').formValidation('enableFieldValidators', 'countriesok[]').formValidation('enableFieldValidators', 'countriesno[]');
         $('#bonusform').formValidation('revalidateField', 'countriesok[]').formValidation('revalidateField', 'countriesno[]'); 
         
         } else {
          $('#bonusform').formValidation('enableFieldValidators', 'countriesok[]',false).formValidation('enableFieldValidators', 'countriesno[]',false);
         //console.log('both null');
         
         }
         
         
       
    }
            
            }).css({"display": "block", "visibility": "hidden"})
            .end()
           .on('success.form.fv', function(e) {
            // Prevent form submission
            e.preventDefault();
            
            
            var label = $('[name="type"] option:selected').text();
            
            var $form = $(e.target),
                fv    = $form.data('formValidation');
                
            $('input, select, textarea', $form).each(function(){
    $(this).val() == "" && $(this).remove();
});
            
            var serialized = $form.serialize();
                                           
            var href = '/index.php?option=com_bonuses&lang=en&task=actions.insert_indirect&label='+ label + '&' + serialized;
            // Use Ajax to submit form data
            $.ajax({
                url: '/index.php?option=com_bonuses&lang=en&task=actions.insert_indirect&label='+ label,
                type: 'POST',
                data: serialized,
                success: function(result) {
                   alert(serialized);
                   document.location = href;
                                     
                }
            });
        }).on('change', '[name="casino"]', function(r) {
    
    $.ajax({    
                url: '/components/com_bonuses/helpers/related.php?cid=' + $(this).val(),
                type: 'GET',
                success: function(rel) {
                    $('#related').html(rel);
                }
            });
    
    
            
            }).on('change', '[name="currency"]', function() {
            var txt = $("option:selected", this).text();
            $('#curr-val, #curr-val-b, #curr-val-max-c, #curr-val-min-c,#curr-val-min-dep, #curr-val-max-dep').text(txt);
            
            
            })
            .end();
// You don't need to care about these methods
    function adjustByHeight() {
        var $body   = $('body'),
            $iframe = $body.data('iframe.fv');
        if ($iframe) {
            // Adjust the height of iframe when hiding the picker
            $iframe.height($body.height());
        }
    }

    function adjustByScrollHeight() {
        var $body   = $('body'),
            $iframe = $body.data('iframe.fv');
        if ($iframe) {
            // Adjust the height of iframe when showing the picker
            $iframe.height($body.get(0).scrollHeight);
        }
    }
    
$('#datetimepicker1').on('dp.change dp.show', function(e) {
        $('#bonusform').formValidation('revalidateField', 'start');
    });
$('#datetimepicker2').on('dp.change dp.show', function(e) {
        $('#bonusform').formValidation('revalidateField', 'end');
    });

$(function () {
    $('input[name=wrmethod]').change(function(){

      switch ($(this).val()) {
               case 'b':
               $('#wr-val').text('X Bonus');  
               break;
               
               case 'bd':
               $('#wr-val').text('X Bonus + Deposit');
               break;
               }
      
      })
});
           
});
</script>