<?php

chdir('../..');
require('includes/application_top.php');
require('includes/templates/responsive_classic/common/html_header.php');
require('includes/templates/responsive_classic/common/tpl_header.php');
include(DIR_WS_CLASSES . 'order.php');
$order = new order;
?>
    <link rel="stylesheet" href="<?php echo DIR_WS_MODULES . 'payment/twocheckout/api/css/twocheckout.css'; ?>" />
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
	<script type="text/javascript" src="https://2pay-js.2checkout.com/v1/2pay.js?v=<?php echo time() ?>"></script>
    <script type="text/javascript" src="<?php echo DIR_WS_MODULES . 'payment/twocheckout/api/js/twocheckout.js'; ?>"></script>
        <div id="contentMainWrapper">
        <div id="tcoApiForm">
            <div id="tcoWait">
                <div class="text">
                    <img src="images/twocheckout/tco_spinner.gif">
                    Processing, please wait!
                </div>
            </div>
            <form id="tco-payment-form" data-json="<?php echo str_replace("\"", "'", MODULE_PAYMENT_TWOCHECKOUT_API_STYLE); ?>">
                <div id="card-element">
                    <!-- A TCO IFRAME will be inserted here. -->
                </div>
                <button class="btn btn-primary pull-right" id="placeOrderTco" data-text="Confirm Order">Confirm Order</button>
                <div class="clearfix"></div>
            </form>
        </div>
    </div>
	<script>

        let seller_id = '<?php echo MODULE_PAYMENT_TWOCHECKOUT_API_SELLER_ID; ?>',
            customer = "<?php echo $order->billing['firstname'] . ' ' . $order->billing['lastname'] ?>",
            default_style = '<?php echo (MODULE_PAYMENT_TWOCHECKOUT_API_DEFAULT_STYLE == 'True') ? 'yes':'no'; ?>',
            action_url = 'extras/twocheckout_api/callback.php';
        $(document).ready(function () {
            tcoLoaded();
        });

	</script>
<?php
require('includes/templates/responsive_classic/common/tpl_footer.php');
