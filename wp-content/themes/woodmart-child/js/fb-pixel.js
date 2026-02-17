function setFbEventCookie(name, value, seconds) {
    const expires = new Date(Date.now() + seconds * 1000).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
}

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
                    const productPrice = btn.getAttribute('data-product_price') || null;

                    const eventId = pId + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

                    setFbEventCookie('fb_atc_event_id', eventId, 10);

                    const params = {
                        content_ids: [pId.toString()],
                        content_type: 'product'
                    };

                    if (productPrice) {
                        params.value = parseFloat(productPrice);
                        params.currency = 'PLN';
                    }

                    fbq('track', 'AddToCart', params, { eventID: eventId });

                    console.log('FB_DEBUG: AddToCart fired for ID:', pId, 'EventID:', eventId);
                } else {
                    console.warn('FB_DEBUG: Button clicked, but Product ID not found');
                }
            }
        } catch (err) { 
            console.error('FB_ERROR AddToCart:', err); 
        }
    });
});