jQuery(document).ready(function($) {

    const $deviceSearch = $('#ppe_device_search');
    const $selectedDeviceId = $('#ppe_selected_device_id');
    const $attributesContainer = $('#ppe-attributes-container');
    const $estimatedPrice = $('#ppe_estimated_price');

    // You could show a loader/spinner if you wish:
    // (Assume there's a hidden loader in HTML with ID #ppe_loader)
    // For now let's skip a visible loader to keep it simple.

    // Debounce function for autocomplete
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    function initDeviceAutocomplete() {
        $deviceSearch.autocomplete({
            source: debounce(function(request, response) {
                // Show loader if needed
                $.ajax({
                    url: ppe_ajax_obj.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ppe_search_devices',
                        search: request.term,
                        _ajax_nonce: ppe_ajax_obj.nonce
                    },
                    success: function(data) {
                        if (data && data.length > 0) {
                            response(data.map(item => ({
                                label: `${item.device_name} (${item.brand || ''})`,
                                value: item.value,
                                device_id: item.device_id
                            })));
                        } else {
                            response([{ label: 'No devices found', value: '' }]);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Autocomplete error:', error);
                        response([{ label: 'Error searching devices', value: '' }]);
                    },
                    complete: function() {
                        // Hide loader if needed
                    }
                });
            }, 300), // Debounce
            minLength: 2,
            select: function(event, ui) {
                if (ui.item && ui.item.device_id) {
                    $selectedDeviceId.val(ui.item.device_id);
                    calculatePrice();
                } else {
                    $selectedDeviceId.val('');
                    $estimatedPrice.text('');
                }
            }
        }).focus(function() {
            const val = $(this).val();
            if (val === 'No devices found' || val === 'Error searching devices') {
                $(this).val('');
            }
        });
    }

    function calculatePrice() {
        const device_id = $selectedDeviceId.val();
        if (!device_id) {
            $estimatedPrice.text('Please select a device.');
            return;
        }

        const selected_attr = {};
        $attributesContainer.find('select').each(function() {
            const attr_id = $(this).data('attribute-id');
            const opt_id = $(this).val();
            if (opt_id) {
                selected_attr[attr_id] = opt_id;
            }
        });

        $estimatedPrice.text('Calculating...');

        $.ajax({
            url: ppe_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ppe_calculate_price',
                device_id: device_id,
                selected_attr: selected_attr,
                _ajax_nonce: ppe_ajax_obj.nonce
            },
            success: function(res) {
                if (res && res.success && res.data && typeof res.data.final_price !== 'undefined') {
                    $estimatedPrice.text(res.data.final_price);
                } else {
                    $estimatedPrice.text('Error calculating price.');
                    console.error('Unexpected response:', res);
                }
            },
            error: function(xhr, status, error) {
                $estimatedPrice.text('Error: ' + error);
                console.error('AJAX error:', status, error);
            }
        });
    }

    // Initialize
    initDeviceAutocomplete();

    // Manual button
    $('#ppe_calculate_btn').on('click', calculatePrice);

    // Recalculate on any attribute change
    $attributesContainer.on('change', 'select', calculatePrice);

});
