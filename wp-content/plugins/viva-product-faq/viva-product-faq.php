<?php
/**
 * Plugin Name: Viva Product FAQ
 * Description: Display product-specific FAQs with text/emoji icons using ACF fields and Schema.org markup
 * Version: 1.2.0
 * Author: Viva Market
 * Text Domain: viva-product-faq
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main Viva Product FAQ Plugin Class
 *
 * Handles FAQ display on product pages with:
 * - ACF field integration
 * - Schema.org FAQPage structured data
 * - Expandable details elements
 * - Configurable FAQ limits
 */
class Viva_Product_FAQ {

    /**
     * Singleton instance
     * @var Viva_Product_FAQ|null
     */
    private static $instance = null;

    /**
     * Stores FAQ data for schema output
     * @var array
     */
    private $faq_data_for_schema = [];

    /**
     * Flag to ensure CSS is output only once
     * @var bool
     */
    private $styles_printed = false;

    /**
     * Get singleton instance
     *
     * @return Viva_Product_FAQ
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register hooks
     */
    private function __construct() {
        add_shortcode('product_faq', [$this, 'render_shortcode']);
        add_action('wp_footer', [$this, 'output_schema']);

        // Register ACF fields programmatically
        add_action('acf/init', [$this, 'register_acf_fields']);
    }

    /**
     * Render [product_faq] shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        // Get current product ID
        $product_id = get_the_ID();

        if (!$product_id) {
            return '';
        }

        // Get FAQ data
        $faq_items = $this->get_faq_data($product_id);

        if (empty($faq_items)) {
            return '';
        }

        // Store FAQ data for schema output in footer
        $this->faq_data_for_schema[] = [
            'product_id' => $product_id,
            'items' => $faq_items
        ];

        // Build HTML output
        $output = $this->get_styles();
        $output .= '<div class="viva-product-faq">';

        foreach ($faq_items as $faq) {
            $question = esc_html($faq['question']);
            $answer = wp_kses_post($faq['answer']);

            // Build icon HTML if icon exists
            $icon_html = '';
            if (!empty($faq['icon'])) {
                // Check if it's an image URL (contains http, .jpg, .png, etc.)
                if (preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $faq['icon']) || strpos($faq['icon'], 'http') === 0) {
                    // It's an image URL
                    $icon_html = sprintf('<img src="%s" alt="%s" class="viva-faq-icon viva-faq-icon-image" loading="lazy">',
                        esc_url($faq['icon']),
                        esc_attr($question)
                    );
                } else {
                    // It's text (emoji or short text)
                    $icon_html = sprintf('<span class="viva-faq-icon viva-faq-icon-text">%s</span>',
                        esc_html($faq['icon'])
                    );
                }
            }

            $output .= sprintf(
                '<details class="viva-faq-item">
                    <summary class="viva-faq-question">
                        %s
                        <span class="viva-faq-question-text">%s</span>
                    </summary>
                    <div class="viva-faq-answer">%s</div>
                </details>',
                $icon_html,
                $question,
                $answer
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Get FAQ data from ACF fields
     *
     * @param int $product_id Product post ID
     * @return array Array of FAQ items with 'question', 'answer', and 'icon' keys
     */
    private function get_faq_data($product_id) {
        // Allow themes/plugins to adjust max FAQ count
        $max_faqs = apply_filters('viva_product_faq_max_count', 5);

        $faq_items = [];

        for ($i = 1; $i <= $max_faqs; $i++) {
            $question = get_field("viva_faq_question_{$i}", $product_id);
            $answer = get_field("viva_faq_answer_{$i}", $product_id);
            $icon = get_field("viva_faq_icon_{$i}", $product_id);

            // Only include if both question and answer have content
            if (!empty($question) && !empty($answer)) {
                $faq_items[] = [
                    'question' => $question,
                    'answer' => $answer,
                    'icon' => $icon // Can be empty
                ];
            }
        }

        return $faq_items;
    }

    /**
     * Get inline styles for FAQ display
     * Outputs CSS only once per page load
     *
     * @return string CSS styles or empty string if already printed
     */
    private function get_styles() {
        if ($this->styles_printed) {
            return '';
        }

        $this->styles_printed = true;

        return '<style>
            /* Mobile-first FAQ styles */
            .viva-product-faq {
                margin: 0;
            }

            .viva-faq-item {
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 12px;
                transition: all 0.3s ease;
            }

            .viva-faq-item:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .viva-faq-question {
                padding: 14px 50px 14px 16px; /* Mobile: more space on right for + icon */
                font-weight: 600;
                font-size: 15px;
                cursor: pointer;
                list-style: none;
                user-select: none;
                position: relative;
                display: flex;
                align-items: center;
                gap: 12px; /* Space between icon and text */
                line-height: 1.4;
            }

            .viva-faq-question::-webkit-details-marker {
                display: none;
            }

            /* FAQ Icon - Base styles */
            .viva-faq-icon {
                flex-shrink: 0; /* Prevent icon from shrinking */
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Text Icon (emoji or short text) */
            .viva-faq-icon-text {
                font-size: 24px; /* Mobile: text/emoji size */
                line-height: 1;
                min-width: 28px;
            }

            /* Image Icon */
            .viva-faq-icon-image {
                width: 24px; /* Mobile: smaller for images */
                height: 24px;
                object-fit: contain;
            }

            /* Question text wrapper */
            .viva-faq-question-text {
                flex: 1;
                padding-right: 8px; /* Extra space so text doesn\'t touch + icon */
            }

            /* Plus/Minus toggle icon */
            .viva-faq-question::after {
                content: "+";
                position: absolute;
                right: 16px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 28px; /* Bigger + icon */
                font-weight: 300;
                line-height: 1;
                transition: transform 0.3s ease;
                color: #333;
            }

            .viva-faq-item[open] .viva-faq-question::after {
                transform: translateY(-50%) rotate(45deg);
            }

            .viva-faq-answer {
                padding: 0 16px 16px 16px; /* Mobile: consistent padding */
                line-height: 1.6;
                color: #555;
                font-size: 14px;
            }

            .viva-faq-answer p:last-child {
                margin-bottom: 0;
            }

            /* Tablet and up */
            @media (min-width: 768px) {
                .viva-faq-question {
                    padding: 18px 60px 18px 20px;
                    font-size: 16px;
                    gap: 16px;
                }

                .viva-faq-icon-text {
                    font-size: 28px;
                    min-width: 32px;
                }

                .viva-faq-icon-image {
                    width: 28px;
                    height: 28px;
                }

                .viva-faq-question::after {
                    right: 20px;
                    font-size: 32px;
                }

                .viva-faq-answer {
                    padding: 0 20px 20px 20px;
                    font-size: 15px;
                }
            }

            /* Desktop */
            @media (min-width: 1024px) {
                .viva-faq-question {
                    padding: 20px 70px 20px 24px;
                    gap: 18px;
                }

                .viva-faq-icon-text {
                    font-size: 32px;
                    min-width: 36px;
                }

                .viva-faq-icon-image {
                    width: 32px;
                    height: 32px;
                }
            }
        </style>';
    }

    /**
     * Output Schema.org FAQPage JSON-LD markup
     * Follows Woodmart theme pattern for structured data
     */
    public function output_schema() {
        if (empty($this->faq_data_for_schema)) {
            return;
        }

        foreach ($this->faq_data_for_schema as $faq_set) {
            $schema_items = [];

            foreach ($faq_set['items'] as $faq) {
                $schema_items[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags($faq['answer'])
                    ]
                ];
            }

            if (!empty($schema_items)) {
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $schema_items
                ];

                echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
            }
        }
    }

    /**
     * Register ACF field group for Product FAQ
     * Auto-creates fields so manual ACF setup isn't required
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return; // ACF not installed
        }

        // Allow themes/plugins to adjust max FAQ count
        $max_faqs = apply_filters('viva_product_faq_max_count', 5);

        $fields = [];

        // Generate fields for each FAQ set (icon + question + answer)
        for ($i = 1; $i <= $max_faqs; $i++) {
            // Icon field
            $fields[] = [
                'key' => "field_viva_faq_icon_{$i}",
                'label' => sprintf(__('Icon %d', 'viva-product-faq'), $i),
                'name' => "viva_faq_icon_{$i}",
                'type' => 'text',
                'instructions' => sprintf(__('Enter emoji icon for question %d (optional). <a href="https://emojipedia.org/" target="_blank">Browse emoji icons →</a>', 'viva-product-faq'), $i),
                'required' => 0,
                'placeholder' => '',
                'wrapper' => [
                    'width' => '100',
                ],
            ];

            // Question field
            $fields[] = [
                'key' => "field_viva_faq_question_{$i}",
                'label' => sprintf(__('Question %d', 'viva-product-faq'), $i),
                'name' => "viva_faq_question_{$i}",
                'type' => 'text',
                'instructions' => sprintf(__('Enter question %d (leave empty to hide this FAQ)', 'viva-product-faq'), $i),
                'required' => 0,
                'wrapper' => [
                    'width' => '100',
                ],
            ];

            // Answer field
            $fields[] = [
                'key' => "field_viva_faq_answer_{$i}",
                'label' => sprintf(__('Answer %d', 'viva-product-faq'), $i),
                'name' => "viva_faq_answer_{$i}",
                'type' => 'wysiwyg',
                'instructions' => sprintf(__('Enter answer %d', 'viva-product-faq'), $i),
                'required' => 0,
                'tabs' => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'delay' => 0,
                'wrapper' => [
                    'width' => '100',
                ],
            ];
        }

        acf_add_local_field_group([
            'key' => 'group_viva_product_faq',
            'title' => __('Viva Product FAQ', 'viva-product-faq'),
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'product',
                    ],
                ],
            ],
            'menu_order' => 10,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => __('Add frequently asked questions for this product. Only filled Q&A pairs will be displayed.', 'viva-product-faq'),
        ]);
    }
}

// Initialize plugin
Viva_Product_FAQ::get_instance();
