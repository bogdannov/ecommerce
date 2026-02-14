document.addEventListener('DOMContentLoaded', function() {
    try {
        // Check for purchase data first, then localized data
        const pixelData = (typeof fb_data !== 'undefined') ? fb_data : (typeof fb_data_localized !== 'undefined' ? fb_data_localized : null);

        if (pixelData && pixelData.event) {
            const options = pixelData.eventID ? { eventID: pixelData.eventID } : {};
            
            console.log('FB_DEBUG: Firing event:', pixelData.event, pixelData.params, options);
            fbq('track', pixelData.event, pixelData.params, options);
        }
    } catch (error) {
        console.error('FB_ERROR:', error);
    }

// AddToCart Tracking (Universal for Woodmart)
    document.addEventListener('click', function(e) {
        try {
            // Ищем ближайшую кнопку, которая может быть "Add to Cart"
            const btn = e.target.closest('.add_to_cart_button') || 
                        e.target.closest('.single_add_to_cart_button') ||
                        e.target.closest('.wd-sticky-add-to-cart');

            if (btn) {
                // Пытаемся достать ID из атрибута кнопки
                let pId = btn.getAttribute('data-product_id');

                // Если ID нет в кнопке (бывает в вариативных товарах), ищем в скрытом поле формы
                if (!pId) {
                    const form = btn.closest('form.cart');
                    pId = form ? form.querySelector('input[name="add-to-cart"]')?.value : null;
                }

                if (pId) {
                    fbq('track', 'AddToCart', {
                        content_ids: [pId.toString()],
                        content_type: 'product'
                    });
                    console.log('FB_DEBUG: AddToCart fired for ID:', pId);
                } else {
                    console.warn('FB_DEBUG: Button clicked, but Product ID not found');
                }
            }
        } catch (err) { 
            console.error('FB_ERROR AddToCart:', err); 
        }
    });
});