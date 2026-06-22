/**
 * Adds client-side format validation for the iwocaPay credential fields to
 * Magento's jQuery validation layer (mage/validation).
 *
 * Hooking into mage/validation - rather than a standalone submit listener -
 * matters because the admin "Save Config" button submits the form with a
 * native form.submit() call, which bypasses addEventListener('submit', ...).
 * Registering proper validation rules instead means a malformed token or
 * Seller ID:
 *   - is flagged inline next to the offending field, and
 *   - blocks the save natively, so it never reaches the server (no more 500
 *     from connection_check choking on a one-character Seller ID).
 *
 * This is the convenience layer only; the authoritative check still happens
 * server-side on save (Plugin/Config/ValidateCredentialsOnSave.php).
 *
 * Magento has a single credential set, so there is one token/Seller ID pair to
 * validate and no production/sandbox environment switching.
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    // Seller Access Token: 40 hex characters.
    var TOKEN_REGEX = /^[a-f0-9]{40}$/i;
    // Seller ID: a UUID.
    var SELLER_ID_REGEX =
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    // An obscured value (Magento renders a stored token as asterisks) means the
    // field was left untouched, so there is nothing to validate.
    var OBSCURED_REGEX = /^\*+$/;

    // Magento renders config field ids as <section>_<group>_..._<field>.
    var TOKEN_ID = 'payment_iwocapay_iwocapay_required_seller_access_token';
    var SELLER_ID_ID = 'payment_iwocapay_iwocapay_required_seller_id';

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? $.trim(el.value) : '';
    }

    /**
     * Whether the token and Seller ID look entered the wrong way round (the
     * token field holds a UUID and the Seller ID field holds a 40-char key).
     */
    function looksSwapped() {
        return SELLER_ID_REGEX.test(fieldValue(TOKEN_ID)) &&
            TOKEN_REGEX.test(fieldValue(SELLER_ID_ID));
    }

    return function () {
        $.validator.addMethod(
            'validate-iwocapay-token',
            function (value) {
                // Empty is handled by required-entry / the server; an obscured
                // value means it was left unchanged.
                if (value === '' || OBSCURED_REGEX.test(value)) {
                    return true;
                }
                return TOKEN_REGEX.test($.trim(value));
            },
            function () {
                if (looksSwapped()) {
                    return $t(
                        'Your Seller Access Token and Seller ID look like they are the wrong way round.'
                    );
                }
                return $t(
                    'The Seller Access Token should be a 40-character key, ' +
                    'e.g. 0123456789abcdef0123456789abcdef01234567.'
                );
            }
        );

        $.validator.addMethod(
            'validate-iwocapay-seller-id',
            function (value) {
                if (value === '') {
                    return true;
                }
                return SELLER_ID_REGEX.test($.trim(value));
            },
            function () {
                if (looksSwapped()) {
                    return $t(
                        'Your Seller Access Token and Seller ID look like they are the wrong way round.'
                    );
                }
                return $t(
                    'The Seller ID should be an ID with dashes, ' +
                    'e.g. 123e4567-e89b-12d3-a456-426614174000.'
                );
            }
        );

        // The config form initialises validation with onfocusout/onkeyup off,
        // so errors would otherwise only appear on a save attempt. Bind live
        // validation for our two fields so the seller gets feedback as they
        // type / leave a field. Validating one field also refreshes the other,
        // so the "wrong way round" message clears from both once corrected.
        $(function () {
            var $token = $('#' + TOKEN_ID);
            var $sellerId = $('#' + SELLER_ID_ID);
            if (!$token.length && !$sellerId.length) {
                return;
            }

            var $form = $('#config-edit-form');

            function revalidate() {
                var validator = $form.data('validator');
                if (!validator) {
                    return;
                }
                if ($token.length) {
                    validator.element($token[0]);
                }
                if ($sellerId.length) {
                    validator.element($sellerId[0]);
                }
            }

            $token.add($sellerId).on('blur keyup', revalidate);
        });
    };
});
