<?php
/**
 * WARNING: Do not edit by hand, this file was generated by Crank:
 *
 * https://github.com/gocardless/crank
 */

namespace GoCardlessPro\Resources;

/**
 * A thin wrapper around a billing_request_template, providing access to its
 * attributes
 *
 * @property-read $authorisation_url
 * @property-read $created_at
 * @property-read $id
 * @property-read $mandate_request_currency
 * @property-read $mandate_request_metadata
 * @property-read $mandate_request_scheme
 * @property-read $mandate_request_verify
 * @property-read $metadata
 * @property-read $name
 * @property-read $payment_request_amount
 * @property-read $payment_request_currency
 * @property-read $payment_request_description
 * @property-read $payment_request_metadata
 * @property-read $payment_request_scheme
 * @property-read $redirect_uri
 * @property-read $updated_at
 */
class BillingRequestTemplate extends BaseResource
{
    protected $model_name = "BillingRequestTemplate";

    /**
     * Permanent URL that customers can visit to allow them to complete a flow
     * based on this template, before being returned to the `redirect_uri`.
     */
    protected $authorisation_url;

    /**
     * Fixed [timestamp](#api-usage-time-zones--dates), recording when this
     * resource was created.
     */
    protected $created_at;

    /**
     * Unique identifier, beginning with "BRT".
     */
    protected $id;

    /**
     * [ISO 4217](http://en.wikipedia.org/wiki/ISO_4217#Active_codes) currency
     * code. Currently only "GBP" is supported as we only have one scheme that
     * is per_payment_authorised.
     */
    protected $mandate_request_currency;

    /**
     * Key-value store of custom data that will be applied to the mandate
     * created when this request is fulfilled. Up to 3 keys are permitted, with
     * key names up to 50 characters and values up to 500 characters.
     */
    protected $mandate_request_metadata;

    /**
     * A Direct Debit scheme. Currently "ach", "autogiro", "bacs", "becs",
     * "becs_nz", "betalingsservice", "pad" and "sepa_core" are supported.
     */
    protected $mandate_request_scheme;

    /**
     * Verification preference for the mandate. One of:
     * <ul>
     *   <li>`minimum`: only verify if absolutely required, such as when part of
     * scheme rules</li>
     *   <li>`recommended`: in addition to minimum, use the GoCardless risk
     * engine to decide an appropriate level of verification</li>
     *   <li>`when_available`: if verification mechanisms are available, use
     * them</li>
     *   <li>`always`: as `when_available`, but fail to create the Billing
     * Request if a mechanism isn't available</li>
     * </ul>
     * 
     * If not provided, the `recommended` level is chosen.
     */
    protected $mandate_request_verify;

    /**
     * Key-value store of custom data. Up to 3 keys are permitted, with key
     * names up to 50 characters and values up to 500 characters.
     */
    protected $metadata;

    /**
     * Name for the template. Provides a friendly human name for the template,
     * as it is shown in the dashboard. Must not exceed 255 characters.
     */
    protected $name;

    /**
     * Amount in minor unit (e.g. pence in GBP, cents in EUR).
     */
    protected $payment_request_amount;

    /**
     * [ISO 4217](http://en.wikipedia.org/wiki/ISO_4217#Active_codes) currency
     * code. Currently only "GBP" is supported as we only have one scheme that
     * is per_payment_authorised.
     */
    protected $payment_request_currency;

    /**
     * A human-readable description of the payment. This will be displayed to
     * the payer when authorising the billing request.
     */
    protected $payment_request_description;

    /**
     * Key-value store of custom data that will be applied to the payment
     * created when this request is fulfilled. Up to 3 keys are permitted, with
     * key names up to 50 characters and values up to 500 characters.
     */
    protected $payment_request_metadata;

    /**
     * A Direct Debit scheme. Currently "ach", "autogiro", "bacs", "becs",
     * "becs_nz", "betalingsservice", "pad" and "sepa_core" are supported.
     */
    protected $payment_request_scheme;

    /**
     * URL that the payer can be redirected to after completing the request
     * flow.
     */
    protected $redirect_uri;

    /**
     * Dynamic [timestamp](#api-usage-time-zones--dates) recording when this
     * resource was last updated.
     */
    protected $updated_at;

}
