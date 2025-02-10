jQuery(document).ready(function($) {
    const $deviceSearch = $('#ppe_device_search');
    const $selectedDeviceId = $('#ppe_selected_device_id');
    const $selectedDeviceBrand = $('#ppe_selected_device_brand');
    const $attributesContainer = $('#ppe-attributes-container');
    const $criteriaContainer = $('#ppe-criteria-container');
    const $estimatedPrice = $('#ppe_estimated_price');

    // Debounce utility
    const debounce = (func, delay) => {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    };

    // Cache for search results
    let deviceCache = {};

    function initDeviceAutocomplete() {
        $deviceSearch.autocomplete({
            source: debounce(function(request, response) {
                if (deviceCache[request.term]) {
                    response(deviceCache[request.term]);
                    return;
                }
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
                        const results = data && data.length > 0
                            ? data.map(item => ({
                                  label: `${item.label} (${item.brand || ''})`,
                                  value: item.value,
                                  device_id: item.device_id,
                                  brand: item.brand
                              }))
                            : [{ label: 'No devices found', value: '' }];
                        deviceCache[request.term] = results;
                        response(results);
                    },
                    
    error: function(xhr, status, error) {
        console.error('AJAX Error:', error);
    
                        response([{ label: `Error: ${xhr.statusText}`, value: '' }]);
                    }
                });
            }, 200),
            minLength: 2,
            select: function(event, ui) {
                if (ui.item && ui.item.device_id) {
                    $selectedDeviceId.val(ui.item.device_id);
                    $selectedDeviceBrand.val(ui.item.brand);

                    // Load evaluation criteria for the selected brand
                    loadCriteriaForBrand(ui.item.brand);

                    // Optionally, load attributes for the device
                    loadAttributesForDevice(ui.item.device_id);

                    $estimatedPrice.text('');
                } else {
                    $selectedDeviceId.val('');
                    $selectedDeviceBrand.val('');
                    $criteriaContainer.empty();
                    $attributesContainer.empty();
                    $estimatedPrice.text('');
                }
            }
        });
    }

    function loadAttributesForDevice(deviceId) {
        $attributesContainer.html('<p>Loading attributes...</p>');
        $.ajax({
            url: ppe_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ppe_get_attributes_by_device',
                device_id: deviceId,
                _ajax_nonce: ppe_ajax_obj.nonce
            },
            success: function(res) {
                $attributesContainer.html(res.success && res.data.html ? res.data.html : '<p>No attributes found.</p>');
            },
            error: function() {
                $attributesContainer.html('<p>Error loading attributes.</p>');
            }
        });
    }

    function loadCriteriaForBrand(brand) {
        $criteriaContainer.html('<p>Loading evaluation criteria...</p>');
        $.ajax({
            url: ppe_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ppe_get_criteria_by_brand',
                brand: brand,
                _ajax_nonce: ppe_ajax_obj.nonce
            },
            success: function(res) {
                if (res.success && res.data && res.data.html) {
                    $criteriaContainer.html(res.data.html);
                } else {
                    $criteriaContainer.html('<p>No criteria found.</p>');
                }
            },
            error: function() {
                $criteriaContainer.html('<p>Error loading criteria.</p>');
            }
        });
    }

    function calculatePrice() {
        const device_id = $selectedDeviceId.val();
        if (!device_id) {
            $estimatedPrice.text('Please select a device.');
            return;
        }
        
        // Collect criteria responses
        const criteriaResponses = {};
        $criteriaContainer.find('.ppe-criteria-block').each(function() {
            const nameAttr = $(this).find('input[type="radio"]').attr('name');
            // name is in format "criteria_<ID>"
            
    if (nameAttr && nameAttr.includes('_')) {
        const criteriaId = nameAttr.split('_')[1];
        const answer = $(this).find('input[type="radio"]:checked').val() || 'no';
        criteriaResponses[criteriaId] = answer;
    }
    
            const answer = $(this).find('input[type="radio"]:checked').val();
            criteriaResponses[criteriaId] = answer;
        });
        
        $estimatedPrice.text('Calculating...');
        
        $.ajax({
            url: ppe_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ppe_calculate_price',
                device_id: device_id,
                selected_criteria: criteriaResponses,
                _ajax_nonce: ppe_ajax_obj.nonce
            },
            success: function(res) {
                $estimatedPrice.text(res?.data?.final_price ?? 'Error calculating price.');
            },
            
    error: function(xhr, status, error) {
        console.error('AJAX Error:', error);
    
                $estimatedPrice.text(`Error: ${xhr.statusText}`);
            }
        });
    }

    initDeviceAutocomplete();
    $('#ppe_calculate_btn').on('click', calculatePrice);
});