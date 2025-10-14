/**
 * HP ShipStation Rates - Admin JavaScript
 *
 * @package HP_ShipStation_Rates
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        /**
         * Test Connection Button
         */
        $('#hp_ss_test_connection').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#hp_ss_test_result');
            var apiKey = $('#hp_ss_api_key').val();
            var apiSecret = $('#hp_ss_api_secret').val();

            // Validate inputs
            if (!apiKey || !apiSecret) {
                $result.html('<span style="color: #dc3232;">❌ ' + 'Please enter both API Key and Secret' + '</span>');
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true).text(hpSsAdmin.testing_text);
            $result.html('<span style="color: #666;">⏳ Testing connection...</span>');

            // Make AJAX request
            $.ajax({
                url: hpSsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hp_ss_test_connection',
                    nonce: hpSsAdmin.nonce,
                    api_key: apiKey,
                    api_secret: apiSecret
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: #dc3232;">❌ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: #dc3232;">❌ Connection test failed. Please try again.</span>');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text(hpSsAdmin.test_connection_text);
                }
            });
        });
    });

})(jQuery);

