jQuery(document).ready(function($) {
    $('#fdsh_test_connection_button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $('#fdsh_test_connection_status');
        var apiUrl = $('#fdsh_client_source_api_url').val();
        var appUsername = $('#fdsh_client_app_username').val();
        var appPassword = $('#fdsh_client_app_password').val();
        var nonce = $('#' + fdsh_admin_vars.test_connection_nonce_name).val(); // Get nonce value using localized name

        // Frontend validation
        if (!apiUrl.trim() || !appUsername.trim() || !appPassword.trim()) {
            $status.html('<span style="color: red;">' + 'Error: API URL, Application Username, and Application Password are all required.' + '</span>').addClass('fdsh-error');
            return; // Stop before making AJAX call
        }

        $status.html('<span style="color: #0073aa;">' + 'Testing...' + '</span>').removeClass('fdsh-success fdsh-error');
        $button.prop('disabled', true);

        $.ajax({
            url: fdsh_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'fdsh_test_connection', // Must match the wp_ajax_ hook in PHP
                fdsh_test_connection_nonce_field: nonce, // The nonce field itself
                api_url: apiUrl,
                app_username: appUsername,
                app_password: appPassword
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">' + response.data + '</span>').addClass('fdsh-success');
                } else {
                    var errorMessage = response.data || 'An unknown error occurred.';
                    if (typeof response.data === 'object' && response.data.message) {
                        errorMessage = response.data.message;
                    } else if (typeof response.data === 'string') {
                        errorMessage = response.data;
                    }
                    $status.html('<span style="color: red;">' + 'Error: ' + errorMessage + '</span>').addClass('fdsh-error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
                if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                     if (typeof jqXHR.responseJSON.data === 'object' && jqXHR.responseJSON.data.message) {
                        errorMessage = jqXHR.responseJSON.data.message;
                     } else if (typeof jqXHR.responseJSON.data === 'string') {
                        errorMessage = jqXHR.responseJSON.data;
                     }
                }
                $status.html('<span style="color: red;">' + errorMessage + '</span>').addClass('fdsh-error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Sync Attributes handler
    $('#fdsh_sync_attributes_button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $('#fdsh_sync_attributes_status');
        var nonce = $('#' + fdsh_admin_vars.sync_attributes_nonce_name).val();

        $status.html('<span style="color: #0073aa;">' + 'Syncing...' + '</span>').removeClass('fdsh-success fdsh-error');
        $button.prop('disabled', true);

        var data = {
            action: 'fdsh_sync_attributes',
        };
        data[fdsh_admin_vars.sync_attributes_nonce_name] = nonce;

        $.ajax({
            url: fdsh_admin_vars.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response && response.success) {
                    $status.html('<span style="color: green;">' + response.data.message + '</span>').addClass('fdsh-success');
                } else {
                    var errorMessage = 'An unknown error occurred.';
                    if (response && response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    $status.html('<span style="color: red;">' + 'Error: ' + errorMessage + '</span>').addClass('fdsh-error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMessage = 'Error: ' + jqXHR.responseJSON.data.message;
                }
                $status.html('<span style="color: red;">' + errorMessage + '</span>').addClass('fdsh-error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // --- Attribute Sync Handlers ---

    var $syncStatus = $('#fdsh_sync_attributes_status');
    
    // 1. Fetch Attributes Button
    $('#fdsh_fetch_attributes_button').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var nonce = $('#' + fdsh_admin_vars.get_attributes_nonce_name).val();

        $button.prop('disabled', true);
        $syncStatus.html('<span style="color: #0073aa;">' + 'Fetching attributes from provider...' + '</span>').removeClass('fdsh-success fdsh-error');

        $.ajax({
            url: fdsh_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'fdsh_get_provider_attributes',
                [fdsh_admin_vars.get_attributes_nonce_name]: nonce
            },
            success: function(response) {
                if (response.success) {
                    var $select = $('#fdsh_attribute_to_sync');
                    $select.empty(); // Clear existing options
                    if (response.data && response.data.length > 0) {
                        $.each(response.data, function(index, attribute) {
                            $select.append($('<option>', {
                                value: attribute.slug,
                                text: attribute.name + ' (' + attribute.slug + ')'
                            }));
                        });
                        $('#fdsh-attribute-selector-container').slideDown();
                        $syncStatus.html('<span style="color: green;">' + 'Fetch successful. Please select an attribute to sync.' + '</span>');
                    } else {
                        $syncStatus.html('<span style="color: orange;">' + 'Provider has no attributes to sync.' + '</span>');
                        $('#fdsh-attribute-selector-container').slideUp();
                    }
                } else {
                     $syncStatus.html('<span style="color: red;">' + 'Error: ' + (response.data.message || 'Could not fetch attributes.') + '</span>');
                }
            },
            error: function(jqXHR) {
                 var errorMessage = 'Error: ' + (jqXHR.responseJSON?.data?.message || 'An unknown AJAX error occurred.');
                 $syncStatus.html('<span style="color: red;">' + errorMessage + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // 2. Sync Selected Attribute Button
    $('#fdsh_sync_selected_attribute_button').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var nonce = $('#' + fdsh_admin_vars.sync_attributes_nonce_name).val();
        var selectedAttribute = $('#fdsh_attribute_to_sync').val();

        if (!selectedAttribute) {
            alert('Please select an attribute to sync.');
            return;
        }

        $button.prop('disabled', true);
        $('#fdsh_fetch_attributes_button').prop('disabled', true); // Disable other button during sync
        $syncStatus.html('<span style="color: #0073aa;">' + 'Syncing attribute ' + selectedAttribute + '...' + '</span>').removeClass('fdsh-success fdsh-error');
        
        var data = {
            action: 'fdsh_sync_attributes',
            attribute_slug: selectedAttribute
        };
        data[fdsh_admin_vars.sync_attributes_nonce_name] = nonce;

        $.ajax({
            url: fdsh_admin_vars.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $syncStatus.html('<span style="color: green;">' + response.data.message + '</span>').addClass('fdsh-success');
                } else {
                    $syncStatus.html('<span style="color: red;">' + 'Error: ' + (response.data.message || 'An unknown error occurred.') + '</span>').addClass('fdsh-error');
                }
            },
            error: function(jqXHR) {
                var errorMessage = 'Error: ' + (jqXHR.responseJSON?.data?.message || 'An unknown AJAX error occurred.');
                $syncStatus.html('<span style="color: red;">' + errorMessage + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $('#fdsh_fetch_attributes_button').prop('disabled', false);
            }
        });
    });

    // Show/hide settings based on plugin role
    function toggleRoleSettings() {
        var role = $('#fdsh_plugin_role').val();
        var $providerInfo = $('#fdsh_provider_info').closest('tr');
        var $clientApiUrl = $('#fdsh_client_source_api_url').closest('tr');
        var $clientAppUsername = $('#fdsh_client_app_username').closest('tr');
        var $clientAppPassword = $('#fdsh_client_app_password').closest('tr');
        var $clientTestButton = $('#fdsh_test_connection_button').closest('tr');

        if (role === 'api_provider') {
            $providerInfo.show();
            $clientApiUrl.hide();
            $clientAppUsername.hide();
            $clientAppPassword.hide();
            $clientTestButton.hide();
        } else if (role === 'api_client') {
            $providerInfo.hide();
            $clientApiUrl.show();
            $clientAppUsername.show();
            $clientAppPassword.show();
            $clientTestButton.show();
        } else { // Should not happen, but hide all specific as a fallback
            $providerInfo.hide();
            $clientApiUrl.hide();
            $clientAppUsername.hide();
            $clientAppPassword.hide();
            $clientTestButton.hide();
        }
    }

    // Initial toggle on page load
    toggleRoleSettings();

    // Toggle when role changes
    $('#fdsh_plugin_role').on('change', function() {
        toggleRoleSettings();
    });
}); 