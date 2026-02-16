<?php
/**
 * Enqueue script and styles for child theme
 */
 
  // Inject FB site confirmation domain
 add_action('wp_head', function() {
    ?>
    <meta name="facebook-domain-verification" content="acci12ns5bpsj5sq0r8mv9no98cgfq" />
    <?php
}, 1);

function allow_unfiltered_uploads_for_admins($caps, $cap, $user_id) {
	if ($cap === 'unfiltered_upload' && current_user_can('administrator')) {
		$caps = ['exist']; // Предоставляем возможность
	}
	return $caps;
}
add_filter('map_meta_cap', 'allow_unfiltered_uploads_for_admins', 10, 3);
function woodmart_child_enqueue_styles() {
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'woodmart-style' ), 10.6 );
}
add_action( 'wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010 );

add_filter('woocommerce_loop_add_to_cart_args', function($args, $product) {
	// Get product data
	$product_name = $product->get_name();
	$product_price = $product->get_price();
	$product_id = $product->get_id();

	// Add custom attrs
	$args['attributes']['data-product_name'] = $product_name;
	$args['attributes']['data-product_price'] = $product_price;
	$args['attributes']['data-product_id'] = $product_id;

	return $args;
}, 10, 2);


function but_now_sticky_single_add_to_cart() {
	global $product;

	if (
            ! $product ||
            ! woodmart_woocommerce_installed() ||
            ! is_product() ||
            ! woodmart_get_opt( 'single_sticky_add_to_cart' ) ||
            ! function_exists('wcpay_express_checkout')
    ) {
		return;
	}
	?>
        <div class="lab-sticky-pay-btn wd-sticky-btn wd-quantity-overlap">
	        <?php
		        wcpay_express_checkout();
	        ?>
        </div>
    <?php
}

add_action( 'woodmart_before_wp_footer', 'but_now_sticky_single_add_to_cart', 999 );

add_action('wp_footer', function() {
	?>
	<script>
        // Events for Google Analytics
        function sendCartGaEvent() {
            document.body.addEventListener('click', function(event) {
                if (event.target.classList.contains('add_to_cart_button') ||
                    event.target.classList.contains('single_add_to_cart_button')
                ) {
                    const button = event.target;
                    const productId = button.getAttribute('data-product_id');
                    const productName = button.getAttribute('data-product_name');
                    const productPrice = button.getAttribute('data-product_price');
                    if (typeof gtag !== 'function') {
                        return;
                    }
                    gtag('event', 'custom_add_to_cart', {
                        items: [
                            {
                                item_id: productId,
                                item_name: productName,
                                price: productPrice,
                                quantity: 1
                            }
                        ]
                    });

                    console.log('Send event:', productName);
                }
            });
        }
        function observeInpost() {
            const targetId = 'selected-parcel-machine-id';

            const billingStreetField = document.getElementById('billing_address_1');
            const billingPostcodeField = document.getElementById('billing_postcode');
            const billingCityField = document.getElementById('billing_city');

            function updateBillingFields() {
                const targetElement = document.getElementById(targetId);
                if (!targetElement) return;
                const content = targetElement.innerHTML.split('<br>');
                console.log(content);
                if (content.length >= 3) {
                    const street = content[1].trim();
                    const postcodeAndCity = content[2].trim().split(' ');
                    const postcode = postcodeAndCity[0].trim();
                    const city = postcodeAndCity[1].trim();

                    if (billingStreetField) billingStreetField.value = street;
                    if (billingPostcodeField) billingPostcodeField.value = postcode;
                    if (billingCityField) billingCityField.value = city;
                }
            }

            const checkInterval = setInterval(() => {
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    console.log('ELEMENT FOUND');
                    clearInterval(checkInterval);
                    const observer = new MutationObserver((mutationsList) => {
                        for (const mutation of mutationsList) {
                            if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                updateBillingFields();
                            }
                        }
                    });
                    observer.observe(targetElement, { childList: true, subtree: true, characterData: true });
                    updateBillingFields();
                }
            }, 500);
        }

         function scrollToFullDescription() {
             const scrollButton = document.getElementById('js_read-more-about');
             const targetElement = document.querySelector('[data-id="anchor1"]');

             if (scrollButton && targetElement) {
                 scrollButton.addEventListener('click', function (event) {
                     event.preventDefault();

                     targetElement.scrollIntoView({
                         behavior: 'smooth',
                         block: 'start',
                     });
                 });
             }
         }

        document.addEventListener('DOMContentLoaded', function() {
            sendCartGaEvent();
            observeInpost();
            scrollToFullDescription();
        });
	</script>
	<?php
});

/**
 * 1. Base Pixel Code - Injected strictly in HEAD
 */
add_action('wp_head', function() {
    if (!class_exists('WooCommerce') || current_user_can('administrator')) return;
    ?>
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '4311413652473070');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=4311413652473070&ev=PageView&noscript=1"
    /></noscript>
    <?php
}, 10);

/**
 * 2. Enqueue JS file and Localize Data
 */
add_action('wp_enqueue_scripts', function() {
    if (!class_exists('WooCommerce') || current_user_can('administrator')) return;

    wp_enqueue_script(
        'fb-pixel-events', 
        get_stylesheet_directory_uri() . '/js/fb-pixel.js', 
        array(), 
        '2.1', // Сбрасываем кэш еще раз
        true 
    );

    $fb_vars = array();
    
    if (is_product()) {
        $product = wc_get_product(get_the_ID());
        if ($product) {
            $fb_vars = array(
                'event' => 'ViewContent',
                'params' => array(
                    'content_ids'  => array((string)$product->get_id()),
                    'content_type' => 'product',
                    'value'        => (float)$product->get_price(),
                    'currency'     => get_woocommerce_currency()
                )
            );
        }
    } elseif (is_checkout() && !is_wc_endpoint_url('order-received')) {
        // Добавляем параметры для InitiateCheckout
        $fb_vars = array(
            'event' => 'InitiateCheckout',
            'params' => array(
                'value'    => (float)WC()->cart->get_total('edit'),
                'currency' => get_woocommerce_currency(),
                'content_type' => 'product'
            )
        );
    }

    if (!empty($fb_vars)) {
        wp_localize_script('fb-pixel-events', 'fb_data_localized', $fb_vars);
    }
}, 20);

/**
 * Handle Purchase Event on Thank You Page
 * Ensures order data and eventID are passed correctly for deduplication
 */
add_action('woocommerce_thankyou', function($order_id) {
    if (current_user_can('administrator') || !$order_id) return;
    
    try {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'id'       => (string)$item->get_product_id(),
                'quantity' => (int)$item->get_quantity()
            );
        }

        $data = array(
            'event'   => 'Purchase',
            'eventID' => (string)$order_id, // Mandatory for future CAPI deduplication
            'params'  => array(
                'value'        => (float)$order->get_total(),
                'currency'     => $order->get_currency(),
                'content_ids'  => array_map('strval', array_values(wp_list_pluck($order->get_items(), 'product_id'))),
                'content_type' => 'product',
                'contents'     => $items
            )
        );

        echo "<script>var fb_data = " . json_encode($data) . ";</script>";
        
    } catch (Exception $e) {
        error_log('FB_PIXEL_PURCHASE_ERROR: ' . $e->getMessage());
    }
});

/**
 * Facebook Conversions API (CAPI) - Purchase Event
 */
add_action('woocommerce_checkout_order_processed', 'send_fb_capi_purchase', 10, 3);

function send_fb_capi_purchase($order_id, $posted_data, $order) {
    $access_token = 'EAAst21IXdAUBQlxZCq2oF7CViif3ZClMD8m8TDDkfMMOfNZB5qLUogG3naHPIcAgFDSRGgtonfVy3h9twuoBlNAJ4rz3ZBNqdloYd49f1KMSp0rbkfkmIpUNyZBQdo8zF8JVWVQfALHC7W5QFhSsi5D5iHgNmGthb74QMIF974IclTTFTvkHCl2AI8ZCZAPhILl2wZDZD'; // Вставь сюда токен
    $pixel_id     = '4311413652473070';
    $test_code    = 'TEST2983'; // Вставь тестовый код или удали строку ниже, если не тестируешь

    if (!$order) return;

    $user_data = array(
        'em' => [hash('sha256', strtolower(trim($order->get_billing_email())))],
        'ph' => [hash('sha256', preg_replace('/\D/', '', $order->get_billing_phone()))],
        'client_ip_address' => $_SERVER['REMOTE_ADDR'],
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'fbc' => $_COOKIE['_fbc'] ?? null,
        'fbp' => $_COOKIE['_fbp'] ?? null,
    );

    $contents = [];
    foreach ($order->get_items() as $item) {
        $contents[] = [
            'id' => (string)$item->get_product_id(),
            'quantity' => (int)$item->get_quantity(),
        ];
    }

    $data = array(
        array(
            'event_name' => 'Purchase',
            'event_time' => time(),
            'event_id'   => (string)$order_id, // ДОЛЖЕН СОВПАДАТЬ С БРАУЗЕРНЫМ ДЛЯ ДЕДУПЛИКАЦИИ
            'user_data'  => array_filter($user_data),
            'custom_data' => array(
                'value'    => (float)$order->get_total(),
                'currency' => $order->get_currency(),
                'content_ids' => array_map('strval', array_values(wp_list_pluck($order->get_items(), 'product_id'))),
                'content_type' => 'product',
                'contents' => $contents,
            ),
            'action_source' => 'website',
            'event_source_url' => wc_get_checkout_order_received_url($order_id),
        )
    );

    $body = array(
        'data' => $data,
        'test_event_code' => $test_code, // Удали эту строку, когда закончишь тесты
    );

    wp_remote_post("https://graph.facebook.com/v18.0/{$pixel_id}/events?access_token={$access_token}", array(
        'body'        => json_encode($body),
        'method'      => 'POST',
        'headers'     => array('Content-Type' => 'application/json'),
        'timeout'     => 15,
    ));
}
