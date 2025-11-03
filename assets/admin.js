jQuery(document).ready(function($){
    if ($('.paypalexpress').is( ':checked' )) {
        $('.wte-paypalexpress-form.settings').fadeIn('slow');
    }
    else{
        $('.wte-paypalexpress-form.settings').fadeOut('slow');
    }
    $('body').on('click', '.paypalexpress', function (e){ 
        if ($('.paypalexpress').is( ':checked' )) {
            $('.wte-paypalexpress-form.settings').fadeIn('slow');
        }
        else{
            $('.wte-paypalexpress-form.settings').fadeOut('slow');
        }
    });
});