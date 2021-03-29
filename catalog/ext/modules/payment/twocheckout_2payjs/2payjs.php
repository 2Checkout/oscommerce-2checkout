<?php
chdir('../../../../');
require('includes/application_top.php');

require(DIR_WS_INCLUDES . 'template_top.php');
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_CONFIRMATION);
global $cart_pm2checkout_2payjs_id, $cart;

include(DIR_WS_CLASSES . 'order.php');
$order = new order;
?>
    <script type="text/javascript" src="https://2pay-js.2checkout.com/v1/2pay.js?v=<?php echo time() ?>"></script>
    <div id="tcoApiForm">
        <div id="tcoWait">
            <div class="text">
                <img src="includes/modules/payment/2co/images/tco_spinner.gif">
                Processing, please wait!
            </div>
        </div>
        <form id="tco-payment-form" data-json="<?php echo str_replace("\"", "'", MODULE_PAYMENT_2CHECKOUT_2PAYJS_STYLE); ?>">
            <div id="card-element">
                <!-- A TCO IFRAME will be inserted here. -->
            </div>
            <button class="btn btn-primary pull-right" id="placeOrderTco" data-text="Confirm Order">Confirm Order</button>
            <div class="clearfix"></div>
        </form>
    </div>
    <script>

        let seller_id = '<?php echo MODULE_PAYMENT_2CHECKOUT_2PAYJS_SELLER_ID; ?>',
            customer = "<?php echo $order->billing['firstname'] . ' ' . $order->billing['lastname'] ?>",
            default_style = '<?php echo (MODULE_PAYMENT_2CHECKOUT_2PAYJS_DEFAULT_STYLE == 'True') ? 'yes':'no'; ?>',
            action_url = 'ext/modules/payment/twocheckout_2payjs/callback.php';
        $(document).ready(function () {
            tcoLoaded();
        });

        function tcoLoaded() {
            window.setTimeout(function () {
                if (window['TwoPayClient']) {
                    console.log('before prepare');
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
                    $.ajax({
                        type: 'POST',
                        url: action_url,
                        data: {ess_token: response.token},
                        dataType: 'json',
                        cache: false
                    }).done(function (response) {
                        if (response.success && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            addError2Co(response.messages);
                        }
                    }).error(function (response) {
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
    </script>
    <style>
        #tcoApiForm {
            position: relative;
            padding: 10px 0px;
        }

        .tco-error li {
            margin-left: 15px;
        }

        .tco-error {
            background: #F32A2A;
            color: #FFF;
            padding: 10px;
            font-size: 12px;
            margin-bottom: 10px;
        }

        #tcoWait .text {
            margin: auto;
            position: relative;
            top: 30%;
            color: #555;
        }

        #tcoWait {
            text-align: center;
            width: 100%;
            display: none;
            height: 100%;
            top: 0;
            left: 0;
            position: absolute;
            color: #4939E4;
            z-index: 99;
            background: rgba(252, 252, 252, 0.7);
        }
    </style>

<?php
require(DIR_WS_INCLUDES . 'template_bottom.php');
require(DIR_WS_INCLUDES . 'application_bottom.php');
