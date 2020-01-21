<?php

use GoCardlessPro\Client;
use GoCardlessPro\Environment;

/**
 * Class GC_Installments_Gateway
 *
 * @package GC_Installments_Gateway
 */
class GC_Installments_Gateway extends WC_Payment_Gateway
{

    /**
     * GC_Installments_Gateway constructor.
     */
    public function __construct()
    {
        $this->id = 'gc-installments-gateway';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'Direct Debit Installments';
        $this->method_description = 'Pay in installments via Direct Debit';

        $this->supports = ['products'];

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->sandbox = 'yes' === $this->get_option('sandbox');
        $this->access_token = $this->get_option('access_token');
        $this->webhook_secret = $this->get_option('webhook_secret');

        $this->min_cart_total_two = $this->get_option('two_installment_minimum');
        $this->min_cart_total_four = $this->get_option('four_installment_minimum');

        // Handles saving settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Register API url: /wc-api/sp-gc-installments/
        add_action('woocommerce_api_sp-gc-installments', array($this, 'handle_requests'));

        // Show extra information in emails and order thank you page
        add_action("woocommerce_email_before_order_table", array($this, 'show_extra_information'), 10, 1);
        add_action("woocommerce_order_details_before_order_table", array($this, 'show_extra_information'), 10, 1);
    }

    /**
     * Setup the payment gateway settings
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'label' => 'Enable Direct Debit Installments',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'description' => 'The text the user sees when selecting the gateway',
                'default' => 'Direct Debit Installments',
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'text',
                'description' => 'The text the user sees when they have selected the gateway',
                'default' => 'Pay via Direct Debit in installments',
            ],
            'webhook_secret' => [
                'title' => 'Webhook Secret',
                'description' => 'Webhook URL: <code>' . $this->_get_webhook_url() . '</code>. Enter your webhook secret here.',
                'type' => 'text',
                'default' => ''
            ],
            'sandbox' => [
                'title' => 'Sandbox',
                'type' => 'checkbox',
                'label' => 'Should we use the Sandbox environment?',
                'description' => '',
                'default' => 'yes',
            ],
            'access_token' => [
                'title' => 'Access Token',
                'type' => 'text',
                'description' => 'GoCardless access token',
                'default' => '',
            ],
            'two_installment_minimum' => [
                'title' => 'Minimum Order for Two Installments',
                'type' => 'number',
                'description' => 'The minimum order amount to allow two installments to be selected',
                'default' => '20.00',
                'custom_attributes' => [
                    'step' => '0.01'
                ]
            ],
            'four_installment_minimum' => [
                'title' => 'Minimum Order for Four Installments',
                'type' => 'number',
                'description' => 'The minimum order amount to allow four installments to be selected',
                'default' => '40.00',
                'custom_attributes' => [
                    'step' => '0.01'
                ]
            ]
        ];
    }

    /**
     * Display what is shown to the user when they select the payment gateway in checkout.
     *
     * We show the description, the dropdown for 2 or 4 installments and information about the number of payments
     * based on the cart total
     */
    public function payment_fields()
    {

        if ($this->description) {
            if ($this->sandbox) {
                $this->description .= '<br />Test mode enabled. Use sort code 20-00-00 and account number 55779911.';
                $this->description = trim($this->description);
            }
            echo wpautop(wp_kses_post($this->description));
        }

        $selectedTwo = $_SESSION['wc-gc-installments-number'] == '2' ? 'selected' : '';
        $selectedFour = $_SESSION['wc-gc-installments-number'] == '4' ? 'selected' : '';

        echo '
            <div id="wc-gc-installments-form" class="wc-payment-form">
                <div class="form-row form-row-wide">
                    <label>Number of Installments</label>
                    <select name="wc-gc-installments-number" style="display: block; width: 100%">
                        <option value="">Choose...</option>
                        <option ' . $selectedTwo . ' value="2">2 Installments (Min Order: £' . $this->min_cart_total_two . ')</option>
                        <option ' . $selectedFour . ' value="4">4 Installments (Min Order: £' . $this->min_cart_total_four . ')</option>
                    </select>
                </div>
            </div>                     
        ';

        global $woocommerce;
        $cartTotal = floatval($woocommerce->cart->get_total('float'));
        $numberOfInstallments = $_SESSION['wc-gc-installments-number'];
        if (!empty($_SESSION['wc-gc-installments-number'])) {
            echo "<p>£" . number_format($cartTotal / $numberOfInstallments, 2) . " per month for " . $numberOfInstallments . " months.</p>";
        }

    }

    /**
     * @return bool
     *
     * Ensure the number of installments is either 2 or 4
     */
    public function validate_fields()
    {
        if (empty($_POST['wc-gc-installments-number'])) {
            wc_add_notice('Number of installments is required!', 'error');
            return false;
        }

        if (!in_array($_POST['wc-gc-installments-number'], [2, 4])) {
            wc_add_notice('Invalid installment number!', 'error');
            return false;
        }

        if (WC()->cart->total < $this->min_cart_total_two && $_POST['wc-gc-installments-number'] == 2) {
            wc_add_notice('The minimum order amount for two installments is £' . $this->min_cart_total_two, 'error');
            return false;
        }

        if (WC()->cart->total < $this->min_cart_total_four && $_POST['wc-gc-installments-number'] == 4) {
            wc_add_notice('The minimum order amount for four installments is £' . $this->min_cart_total_four, 'error');
            return false;
        }

        return true;
    }

    /**
     * @param $order_id
     * @return array
     *
     * Create a redirect flow and redirect to the hosted GC page
     * @throws \GoCardlessPro\Core\Exception\InvalidStateException
     */
    public function process_payment($order_id)
    {
        $redirectUrl = $this->_create_redirect_flow($order_id);

        return [
            'result' => 'success',
            'redirect' => $redirectUrl,
        ];
    }

    /**
     * @param $order_id
     * @return mixed
     * @throws \GoCardlessPro\Core\Exception\InvalidStateException
     *
     * Create redirect flow and add number of installments to post meta
     */
    public function _create_redirect_flow($order_id)
    {
        $order = wc_get_order($order_id);
        $client = new Client([
            'access_token' => $this->access_token,
            'environment' => $this->sandbox ? Environment::SANDBOX : Environment::LIVE
        ]);

        $redirectFlow = $client->redirectFlows()->create([
            'params' => [
                'session_token' => $order->get_order_key(),
                'success_redirect_url' => $this->_get_success_redirect_url($order_id),
                'description' => 'Order #' . $order->get_id() . ' - £' . $order->get_total() . ' over ' . $_POST['wc-gc-installments-number'] . ' equal monthly installments',
                'prefilled_customer' => [
                    'given_name' => $order->billing_first_name,
                    'family_name' => $order->billing_last_name,
                    'email' => $order->billing_email,
                    'company_name' => $order->billing_company,
                    'address_line1' => $order->billing_address_1,
                    'address_line2' => $order->billing_address_2,
                    'city' => $order->billing_city,
                    'postal_code' => $order->billing_postcode,
                ]
            ]
        ]);

        add_post_meta($order_id, 'number_of_installments', $_POST['wc-gc-installments-number']);

        return $redirectFlow->redirect_url;
    }

    /**
     * Handle incoming requests to the WC API
     *
     * @throws \GoCardlessPro\Core\Exception\InvalidStateException
     */
    public function handle_requests()
    {
        switch ($_GET['request']) {
            case 'redirect_flow':
                $this->handle_success_redirect();
                break;

            case 'webhook':
                $this->handle_webhook();
                break;
        }
    }

    /**
     * The customer has given a mandate so complete the redirect flow
     * and create the subscription. Set order to On Hold and reduce stock as necessary.
     *
     * Redirect to thank you page
     *
     * @throws \GoCardlessPro\Core\Exception\InvalidStateException
     */
    public function handle_success_redirect()
    {
        $order = wc_get_order($_GET['order_id']);

        if (!$order) {
            wp_die("Order not found.");
        }

        $this->_complete_redirect_flow_and_create_subscription($order);

        $order->update_status('on-hold');
        $order->reduce_order_stock();

        wp_redirect($this->get_return_url($order));
    }

    /**
     * @param $order
     * @throws \GoCardlessPro\Core\Exception\InvalidStateException
     *
     * Complete redirect flow, create subscription. Add subscription_id to post meta
     */
    public function _complete_redirect_flow_and_create_subscription($order)
    {
        $numberOfInstallments = get_post_meta($order->get_id(), 'number_of_installments')[0];
        $orderTotal = $order->get_total();
        $sessionToken = $order->get_order_key();

        $client = new Client([
            'access_token' => $this->access_token,
            'environment' => $this->sandbox ? Environment::SANDBOX : Environment::LIVE
        ]);

        try {
            $response = $client->redirectFlows()->complete($_GET['redirect_flow_id'], [
                'params' => [
                    'session_token' => $sessionToken
                ]
            ]);

            $subscription = $client->subscriptions()->create([
                'params' => [
                    'name' => 'Order #' . $order->get_id() . ' - £' . $order->get_total() . ' over ' . $numberOfInstallments . ' equal monthly installments',
                    'amount' => ($orderTotal / $numberOfInstallments) * 100,
                    'count' => $numberOfInstallments,
                    'currency' => 'GBP',
                    'interval_unit' => 'monthly',
                    'links' => [
                        'mandate' => $response->links->mandate
                    ]
                ]
            ]);
        } catch (Exception $e) {
            wp_die("Error: " . $e->getMessage() . ". You have not been charged.");
        }

        add_post_meta($order->get_id(), 'subscription_id', $subscription->id);
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook()
    {
        $raw_payload = file_get_contents('php://input');
        $signature = !empty($_SERVER['HTTP_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_WEBHOOK_SIGNATURE'] : '';

        $calc_signature = hash_hmac('sha256', $raw_payload, $this->webhook_secret);

        try {
            if ($signature !== $calc_signature) {
                header('HTTP/1.1 498 Invalid signature');
                throw new Exception('Invalid Signature');
            }

            $payload = json_decode($raw_payload, true);
            if (empty($payload['events'])) {
                header('HTTP/1.1 400 Bad request');
                throw new Exception('Missing Events in Payload');
            }

            $this->_process_webhook_payload($payload);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * @param $payload
     *
     * Process the webhook payload by getting the order and sending it off appropriately
     */
    public function _process_webhook_payload($payload)
    {
        foreach ($payload['events'] as $event) {
            $order = $this->_get_order_from_webhook_event($event);

            if (!$order) {
                return;
            }

            if ($event['resource_type'] == 'subscriptions') {
                $this->_handle_subscription_webhook($order, $event);
            }
        }
    }

    /**
     * @param $event
     * @return |null
     *
     * Find the order from the webhook by searching for the subscription_id
     * in post meta
     */
    public function _get_order_from_webhook_event($event)
    {
        $subscriptionId = $event['links']['subscription'];

        $on_hold_orders = wc_get_orders(array(
            'limit' => -1,
            'status' => 'on-hold',
        ));

        foreach ($on_hold_orders as $order) {
            foreach ($order->meta_data as $metaData) {
                $data = $metaData->get_data();
                if ($data['key'] == "subscription_id" && $data['value'] == $subscriptionId) {
                    return $order;
                }
            }
        }

        return null;
    }

    /**
     * @param $order
     * @param $event
     *
     * Handle various subscription events
     */
    public function _handle_subscription_webhook($order, $event)
    {
        if ($event['action'] == 'finished') {
            $order->payment_complete();
        }

        if ($event['action'] == 'cancelled') {
            $order->update_status('cancelled');
        }
    }

    /**
     * @param $order
     *
     * Extra information to show in order emails and thank you page.
     * Gives a summary for the installments and provide details on the next
     * charges.
     */
    public function show_extra_information($order)
    {
        if ($order->get_payment_method() != "gc-installments-gateway") {
            return;
        }

        $numberOfInstallments = get_post_meta($order->get_id(), 'number_of_installments')[0];
        $orderTotal = $order->get_total();
        $installmentValue = number_format($orderTotal / $numberOfInstallments, 2);
        $subscriptionId = get_post_meta($order->get_id(), 'subscription_id')[0];

        $client = new Client([
            'access_token' => $this->access_token,
            'environment' => $this->sandbox ? Environment::SANDBOX : Environment::LIVE
        ]);

        $subscription = $client->subscriptions()->get($subscriptionId);
        $text = '';

        if (count($subscription->upcoming_payments) > 0) {
            $text = "<hr />This order consists of <strong>{$numberOfInstallments} installments each with a value of £{$installmentValue}</strong>. The payments will be debited as follows: <br /><br />";

            foreach ($subscription->upcoming_payments as $payment) {
                $text .= date_format(date_create($payment->charge_date), "d/m/Y") . ' - £' . number_format($payment->amount / 100, 2) . '<br />';
            }

            $text .= "<br />The order will stay on hold until all payments have been received.<hr />";
        }

        echo $text;
    }

    /**
     * @param $order_id
     * @return mixed
     *
     * Helper function to get the success redirect url based on the WC API:
     *
     * /wc-api/sp-gc-installments/?request=redirect_flow
     */
    public function _get_success_redirect_url($order_id)
    {
        $url = add_query_arg([
            'order_id' => $order_id,
            'request' => 'redirect_flow'
        ], WC()->api_request_url('sp-gc-installments', true));

        return $url;
    }

    /**
     * @return mixed
     *
     * Helper function to get the webhook url based on the WC API:
     *
     * /wc-api/sp-gc-installments/?request=webhook
     */
    public function _get_webhook_url()
    {
        $url = add_query_arg([
            'request' => 'webhook'
        ], WC()->api_request_url('sp-gc-installments', true));

        return $url;
    }
}

/**
 * When the cart is recalculated we check all the products in the cart and check if they are on sale.
 *
 * If they are on sale we check whether the date in the number of installment months will be after the sale finishes.
 * If it will be, then we add a fee to the cart and of the difference between the regular price and the sale price and
 * also show a message for each product that has had a fee added to it.
 *
 * Remove all fees and reset the number of installments whenever this is run.
 *
 * Don't check all the products if the payment method is not installments
 */
add_action('woocommerce_cart_calculate_fees', 'add_fee_if_product_sale_is_before_installments_finish', 20, 1);
function add_fee_if_product_sale_is_before_installments_finish($cart)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (isset($_POST['post_data'])) {
        parse_str($_POST['post_data'], $post_data);
    } else {
        $post_data = $_POST;
    }


    if ($post_data['payment_method'] != 'gc-installments-gateway') {
        return;
    }

    $cart->fees_api()->remove_all_fees();
    $_SESSION['wc-gc-installments-number'] = $post_data['wc-gc-installments-number'] ?? '';

    $cartItem = 0;
    foreach ($cart->get_cart() as $cartProduct) {
        $cartItem++;
        $product = $cartProduct['data'];
        if ($product->is_on_sale()) {
            $finishDate = date(strtotime("+" . $post_data['wc-gc-installments-number'] - 1 . " months"));
            $endTimestamp = $product->date_on_sale_to->getTimestamp();

            if ($finishDate > $endTimestamp) {
                $cart->add_fee("Installment Finishes After Sale Fee - Item #" . $cartItem, $product->get_regular_price() - $product->get_sale_price());
                wc_add_notice("The sale for <strong>" . $product->get_name() . "</strong> will have finished by the time your installments finish. A surcharge has been added to reflect the non-sale price.", 'notice');
            }
        }
    }
}

/**
 * Whenever the number of installments is changed or the payment method is changed we trigger a recalculation
 * of the cart which will run the above code to determine whether any fees need to be added.
 */
add_action('wp_footer', 'bcc_refresh_checkout_script');
function bcc_refresh_checkout_script()
{
    // Only on checkout page
    if (is_checkout() && !is_wc_endpoint_url('order-received')) :
        ?>
        <script type="text/javascript">
            jQuery(function ($) {
                // On payment method change
                $('form.woocommerce-checkout').on('change', 'select[name="wc-gc-installments-number"]', function () {
                    $('body').trigger('update_checkout');
                });

                // On payment method change
                $('form.woocommerce-checkout').on('change', 'input[name="payment_method"]', function () {
                    $('body').trigger('update_checkout');
                });
            })
        </script>
    <?php
    endif;
}
