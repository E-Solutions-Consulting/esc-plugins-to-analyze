
jQuery(document).ready(function(){ 
    jQuery('.tab_one a').click(function(){  
	
		jQuery(".tab_one").removeClass("active-a");
		jQuery(this).parent().addClass("active-a");
		jQuery(".tab_area").hide();
		var tab=jQuery(this).data('id');
		  jQuery('.'+tab).show();
     });
});

