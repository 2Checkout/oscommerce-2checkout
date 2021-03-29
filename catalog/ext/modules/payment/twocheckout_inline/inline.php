<?php
chdir('../../../../');
require('includes/application_top.php');

require(DIR_WS_INCLUDES . 'template_top.php');
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_CONFIRMATION);

?>
    <button style="margin: 35% auto 0;
background: #35ae35;
color: #fff;
display: block;
padding: 15px 25px;
border: 1px solid #35ae35;
border-radius: 5px;
font-size: 17px;" onclick="initInline()">Place order
    </button>
    <script>
        let payload = <?php echo json_encode($_POST);?>;
        initInline();

        function initInline() {
            (function (document, src, libName, config) {
                if (window.hasOwnProperty(libName)) {
                    delete window[libName];
                }
                let script = document.createElement('script');
                script.src = src;
                script.async = true;
                let firstScriptElement = document.getElementsByTagName('script')[0];
                script.onload = function () {
                    for (let namespace in config) {
                        if (config.hasOwnProperty(namespace)) {
                            window[libName].setup.setConfig(namespace, config[namespace]);
                        }
                    }
                    window[libName].register();
                    TwoCoInlineCart.setup.setMerchant(payload['merchant']);
                    TwoCoInlineCart.setup.setMode('DYNAMIC');
                    TwoCoInlineCart.register();
                    TwoCoInlineCart.products.removeAll();
                    TwoCoInlineCart.cart.setAutoAdvance(true);
                    TwoCoInlineCart.cart.setReset(true); // erase previous cart sessions

                    TwoCoInlineCart.cart.setCurrency(payload['currency']);
                    TwoCoInlineCart.cart.setLanguage(payload['language']);
                    TwoCoInlineCart.cart.setReturnMethod(payload['return-method']);
                    TwoCoInlineCart.cart.setTest(payload['test']);
                    TwoCoInlineCart.cart.setOrderExternalRef(payload['order-ext-ref']);
                    TwoCoInlineCart.cart.setExternalCustomerReference(payload['customer-ext-ref']);
                    TwoCoInlineCart.cart.setSource(payload['src']);

                    TwoCoInlineCart.products.addMany(payload['products']);
                    TwoCoInlineCart.billing.setData(payload['billing_address']);
                    TwoCoInlineCart.billing.setCompanyName(payload['billing_address']['company-name']);
                    TwoCoInlineCart.shipping.setData(payload['shipping_address']);
                    TwoCoInlineCart.cart.setSignature(payload['signature']);
                    TwoCoInlineCart.cart.checkout();
                };
                firstScriptElement.parentNode.insertBefore(script, firstScriptElement);
            })(document, 'https://secure.2checkout.com/checkout/client/twoCoInlineCart.js', 'TwoCoInlineCart',
                {'app': {'merchant': payload['merchant']}, 'cart': {'host': 'https:\/\/secure.2checkout.com'}}
            );
        };
    </script>
<?php
require(DIR_WS_INCLUDES . 'template_bottom.php');
require(DIR_WS_INCLUDES . 'application_bottom.php');
