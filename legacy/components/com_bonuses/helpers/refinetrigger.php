<?php
//define constant
defined('_JEXEC') or die('Restricted access'); 

$type = JRequest::getVar('type');


$filtertrigger = '<div class="cd-filter">
			<form>
				<div class="cd-filter-block">
					<h4>Filter By</h4>
					<div class="cd-filter-content">
						<div class="cd-select cd-filters">
							<select class="filter" name="step-one" data-type="'.$type.'">
								<option value="">Choose an option</option>
								<option value="software">Software</option>
								<option value=".option2">Option 2</option>
								<option value=".option3">Option 3</option>
								<option value=".option4">Option 4</option>
							</select>
						</div> <!-- cd-select -->
					</div> <!-- cd-filter-content -->
				</div> <!-- cd-filter-block -->
				
				<div class="cd-filter-block" id="step2">
				</div> <!-- cd-filter-block -->
			</form>

			<span class="cd-close">Close</span>
		</div> <!-- cd-filter -->
<span class="cd-filter-trigger">Filters</span>';

?>
<script>
var option = '';
var type = '';

jQuery(document).ready(function($){
	//open/close lateral filter
	$('.cd-filter-trigger').on('click', function(){
		triggerFilter(true);
	});
	$('.cd-filter .cd-close').on('click', function(){
		triggerFilter(false);
	});

	function triggerFilter($bool) {
		var elementsToTrigger = $([$('.cd-filter-trigger'), $('.cd-filter'), $('.cd-tab-filter'), $('.cd-gallery'), $('.filters-wrapper')]);
		elementsToTrigger.each(function(){
			$(this).toggleClass('filter-is-visible', $bool);
		});
	}

	//mobile version - detect click event on filters tab
	var filter_tab_placeholder = $('.cd-tab-filter .placeholder a'),
		filter_tab_placeholder_default_value = 'Select',
		filter_tab_placeholder_text = filter_tab_placeholder.text();
	
	$('.cd-tab-filter li').on('click', function(event){
		//detect which tab filter item was selected
		var selected_filter = $(event.target).data('type');
			
		//check if user has clicked the placeholder item
		if( $(event.target).is(filter_tab_placeholder) ) {
			(filter_tab_placeholder_default_value == filter_tab_placeholder.text()) ? filter_tab_placeholder.text(filter_tab_placeholder_text) : filter_tab_placeholder.text(filter_tab_placeholder_default_value) ;
			$('.cd-tab-filter').toggleClass('is-open');

		//check if user has clicked a filter already selected 
		} else if( filter_tab_placeholder.data('type') == selected_filter ) {
			filter_tab_placeholder.text($(event.target).text());
			$('.cd-tab-filter').removeClass('is-open');	

		} else {
			//close the dropdown and change placeholder text/data-type value
			$('.cd-tab-filter').removeClass('is-open');
			filter_tab_placeholder.text($(event.target).text()).data('type', selected_filter);
			filter_tab_placeholder_text = $(event.target).text();
			
			//add class selected to the selected filter item
			$('.cd-tab-filter .selected').removeClass('selected');
			$(event.target).addClass('selected');
		}
	});
	
	//close filter dropdown inside lateral .cd-filter 
	$('.cd-filter-block h4').on('click', function(){
		$(this).toggleClass('closed').siblings('.cd-filter-content').slideToggle(300);
	})
	
	$('.cd-filter-block').on('change','[name="step-one"]', function() {
  
          var val = $(this).val();
          type = $(this).attr("data-type");
          
          
          
          option = $(this).val();
          
          var spinner = '<div class="spinner"><div class="bounce1" /><div class="bounce2" /><div class="bounce3" />';
          
          $('#step2').html(spinner);
          
  $.ajax({    
                url: '/components/com_bonuses/helpers/filters.php',
                type: 'POST',
                data: {filter: val, type: type},
                success: function(rel) {
                    $('#step2').html(rel);
                }
            });
            
            
            
});

$('.cd-filter-block').on('change','[name="filter"]', function() {
            
            var filterval = $(this).val();            
            var href = 'https://www.freeextrachips.com/index.php?option=com_bonuses&view=search&filter='+option+'&fq='+filterval+'&type='+type;

            document.location = href;
                        
            });

});


	</script>