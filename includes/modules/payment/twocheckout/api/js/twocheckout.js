function tcoLoaded() {
    window.setTimeout(function () {
        if (window['TwoPayClient']) {
            prepare2PayJs();
        } else {
            tcoLoaded();
        }
    }, 100);
}

function prepare2PayJs() {
    let jsPaymentClient = new TwoPayClient(seller_id);
    if(default_style === 'yes'){
        component = jsPaymentClient.components.create('card')
    }else{
        style = jQuery('#tco-payment-form').data('json');
        style = style.replace(/'/g, '"');
        component = jsPaymentClient.components.create('card', JSON.parse(style));
    }
    component.mount('#card-element');

    // Handle form submission.
    $('body').on('click', '#placeOrderTco', function (event) {
        event.preventDefault();
        $('.tco-error').remove();
        startProcessing2Co();

        jsPaymentClient.tokens.generate(component, {name: customer}).then(function (response) {
            zcJS.ajax({
                type: 'POST',
                url: action_url,
                data: {ess_token: response.token},
                dataType: 'json',
                cache: false,
                timeout: 15000,
            }).done(function (response) {
                if (response.success && response.redirect) {
                    window.location.href = response.redirect;
                } else {
                    addError2Co(response.messages);
                }
            }).fail(function (response) {
                console.error(response);
                addError2Co('Your payment could not be processed. Please refresh the page and try again!');
            });

        }).catch(function (error) {
            if (error.toString() !== 'Error: Target window is closed') {
                console.error(error);
                addError2Co(error.toString());
            }
        });
    });
}

function addError2Co(string) {
    $('#tcoApiForm').prepend('<div class="tco-error">' + string + '</div>');
    stopProcessing2Co();
}

function stopProcessing2Co() {
    $('#placeOrderTco').attr('disabled', false).html($('#placeOrderTco').data('text'));
    $('#tcoWait').hide();
}

function startProcessing2Co() {
    $('#placeOrderTco').attr('disabled', false).html('Processing...');
    $('#tcoWait').show();
}