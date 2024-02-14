<?php

use GoCardlessPro\Client;
use GoCardlessPro\Environment;

/**
 * Plugin Name: GoCardless Installments Gateway
 * Plugin URI: https://www.shivampaw.com/work/bronze-cricket-club
 * Description: A payment gateway that provides installment plans for WooCommerce orders over X in value.
 * Version: 1.0.0
 * Author: Shivam Paw
 * Author URI: https://www.shivampaw.com/
 *
 * @package GC_Installments
 */
class GC_Installments
{

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'), 0);
        add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
    }

    public function register_gateway($gateways)
    {
        $gateways[] = 'GC_Installments_Gateway';
        return $gateways;
    }

    public function init()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        require_once('vendor/autoload.php');
        require_once('includes/class-gc-installments-gateway.php');
    }

    public function register_meta_boxes()
    {
        add_meta_box('gocardless-installments-subscription-events', 'Subscription Details', array($this, 'subscription_events_meta_box'), 'woocommerce_page_wc-orders', 'side', 'core');
    }

    public function subscription_events_meta_box($post)
    {	
        try {
            $order = wc_get_order($post->ID);
			
			if (!$order) {
				echo 'Order not found';
				
				return;
			}

			if ($order->get_payment_method() != "gc-installments-gateway") {
				echo 'Order not paid using GoCardless Instalments';
				
				return;
			}
			
            $gateway = new GC_Installments_Gateway();

            $client = new Client([
                'access_token' => $gateway->get_option('access_token'),
                'environment' => 'yes' === $gateway->get_option('sandbox') ? Environment::SANDBOX : Environment::LIVE
            ]);


            $events = $client->events()->list([
                'params' => [
                    'subscription' => $order->get_meta('subscription_id'),
                ]
            ]);

            $text = '<ul class="order_notes">';
            foreach ($events->records as $record) {
                $text .= '
                    <li class="note system-note">
                        <div class="note_content">
                            <p>' . $record->details->description . '</p><br />';

                if ($record->action == "payment_created") {
                    $text .= '<p>';
                    $payment = $client->payments()->get($record->links->payment);
                    $text .= '<strong>Amount: </strong>£' . number_format($payment->amount / 100, 2) . '<br />';
                    $text .= '<strong>Charge Date: </strong>' . date_format(date_create($payment->charge_date), "d/m/Y") . '<br />';
                    $text .= '<strong>Payment Status: </strong>' . ucwords(str_replace('_', ' ', $payment->status));
                    $text .= '</p><br />';
                }

                $text .= '<p>';
                foreach ($record->links as $k => $v) {
                    $text .= '<strong>' . ucfirst($k) . ': </strong>' . $v . '<br />';
                }
                $text .= '</p>';

                $text .= '</div>
                        <p class="meta"><abbr class="exact-date">' . date_format(date_create($record->created_at), "d/m/Y \a\\t H:i:s") . '</abbr></p>
                    </li>';
            }

            $subscription = $client->subscriptions()->get($order->get_meta('subscription_id'));
            $payments = '<p><strong>Upcoming Payments: </strong></p>';
            $payments .= '<p>';
            foreach ($subscription->upcoming_payments as $payment) {
                $payments .= date_format(date_create($payment->charge_date), "d/m/Y") . ' - £' . number_format($payment->amount / 100, 2) . '<br />';
            }
            $payments .= '</p>';

            $text .= '
                <li class="note system-note">
                    <div class="note_content">
                        <p><strong>Number of Installments:</strong> ' . $order->get_meta('number_of_installments') . '</p>
                        <br />
                        <p><strong>GoCardless Subscription ID:</strong> ' . $order->get_meta('subscription_id') . '</p>
                        <br />' . $payments . '
                    </div>
                </li>
            ';

            $text .= '</ul>';

            echo $text;

        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

}

function sp_gc_installments()
{
    static $instance;

    if (!isset($instance)) {
        $instance = new GC_Installments();
    }

    return $instance;
}

sp_gc_installments();
