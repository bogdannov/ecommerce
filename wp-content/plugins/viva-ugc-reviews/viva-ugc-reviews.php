<?php
/**
 * Plugin Name: Viva UGC Reviews
 * Description: Display curated UGC-style reviews with photos and videos on product pages
 * Version: 1.1.0
 * Author: Viva Market
 * Text Domain: viva-ugc-reviews
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main Viva UGC Reviews Plugin Class
 *
 * Handles UGC review display on product pages with:
 * - ACF field integration (overall rating, total reviews, review repeater)
 * - Responsive photo grid (2-column mobile, 4-column desktop)
 * - Horizontal scrollable video row (9:16 vertical videos)
 * - Dual video format support (WebP + MP4)
 * - Gold star ratings (1-5 stars)
 * - Polish Omnibus directive compliance
 */
class Viva_UGC_Reviews {

    /**
     * Singleton instance
     * @var Viva_UGC_Reviews|null
     */
    private static $instance = null;

    /**
     * Flag to ensure CSS is output only once
     * @var bool
     */
    private $styles_printed = false;

    /**
     * Get singleton instance
     *
     * @return Viva_UGC_Reviews
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
        add_shortcode('product_ugc_reviews', [$this, 'render_shortcode']);

        // Register ACF fields programmatically
        add_action('acf/init', [$this, 'register_acf_fields']);
    }

    /**
     * Render [product_ugc_reviews] shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'limit' => 0,
            'show_videos' => true,
            'show_omnibus' => true,
            'show_overall_rating' => true
        ], $atts, 'product_ugc_reviews');

        // Convert string 'true'/'false' to boolean
        $atts['show_videos'] = filter_var($atts['show_videos'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_omnibus'] = filter_var($atts['show_omnibus'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_overall_rating'] = filter_var($atts['show_overall_rating'], FILTER_VALIDATE_BOOLEAN);
        $atts['limit'] = absint($atts['limit']);

        // Get current product ID
        $product_id = get_the_ID();

        if (!$product_id) {
            return '';
        }

        // Get review data
        $reviews = $this->get_ugc_reviews_data($product_id);

        // Build HTML output
        $output = $this->get_styles();
        $output .= '<div class="viva-ugc-reviews">';

        // Overall rating header (if enabled and data exists)
        if ($atts['show_overall_rating']) {
            $overall_rating = get_field('viva_ugc_overall_rating', $product_id);
            $total_reviews = get_field('viva_ugc_total_reviews', $product_id);

            if ($overall_rating || $total_reviews) {
                $output .= $this->render_overall_rating($overall_rating, $total_reviews);
            }
        }

        // If no individual reviews, show only overall rating
        if (empty($reviews)) {
            $output .= '</div>';
            return $output;
        }

        // Apply limit if specified
        if ($atts['limit'] > 0) {
            $reviews = array_slice($reviews, 0, $atts['limit']);
        }

        // Separate videos from other reviews
        $with_videos = array_filter($reviews, function($review) {
            return !empty($review['video_webp']) || !empty($review['video_mp4']);
        });

        // Video row (if videos exist and enabled)
        if ($atts['show_videos'] && !empty($with_videos)) {
            $output .= $this->render_video_row(array_slice($with_videos, 0, 3));
        }

        // Photo grid (includes all reviews: text-only, image, and video reviews)
        $output .= $this->render_photo_grid($reviews);

        // Omnibus disclaimer
        if ($atts['show_omnibus']) {
            $output .= $this->render_omnibus_disclaimer();
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Get UGC review data from ACF fields
     *
     * @param int $product_id Product post ID
     * @return array Array of review items
     */
    private function get_ugc_reviews_data($product_id) {
        // Allow themes/plugins to adjust max review count
        $max_reviews = apply_filters('viva_ugc_reviews_max_count', 10);

        $reviews = [];

        for ($i = 1; $i <= $max_reviews; $i++) {
            $customer_name = get_field("viva_ugc_review_{$i}_name", $product_id);
            $review_text = get_field("viva_ugc_review_{$i}_text", $product_id);
            $rating = get_field("viva_ugc_review_{$i}_rating", $product_id);

            // Only include if required fields are present
            if (empty($customer_name) || empty($review_text) || empty($rating)) {
                continue;
            }

            $image = get_field("viva_ugc_review_{$i}_image", $product_id);
            $video_webp = get_field("viva_ugc_review_{$i}_video_webp", $product_id);
            $video_mp4 = get_field("viva_ugc_review_{$i}_video_mp4", $product_id);

            $reviews[] = [
                'customer_name' => $customer_name,
                'review_text' => $this->sanitize_review_text($review_text, 100),
                'rating' => absint($rating),
                'image' => $image,
                'video_webp' => $video_webp,
                'video_mp4' => $video_mp4
            ];
        }

        // Allow filtering of review data
        return apply_filters('viva_ugc_reviews_data', $reviews, $product_id);
    }

    /**
     * Sanitize and truncate review text
     *
     * @param string $text Review text
     * @param int $max_length Maximum character length
     * @return string Sanitized text
     */
    private function sanitize_review_text($text, $max_length = 100) {
        $text = wp_strip_all_tags($text);
        if (mb_strlen($text) > $max_length) {
            $text = mb_substr($text, 0, $max_length) . '...';
        }
        return $text;
    }

    /**
     * Render overall rating header
     *
     * @param float $rating Overall rating (e.g., 4.7)
     * @param int $count Total review count
     * @return string HTML output
     */
    private function render_overall_rating($rating, $count) {
        $output = '<div class="viva-ugc-overall-rating">';

        if ($rating) {
            $star_svg = $this->get_star_svg();
            $output .= sprintf(
                '<div class="viva-ugc-overall-stars">
                    <span class="viva-ugc-rating-number">%s</span>
                    <span class="viva-ugc-star-icon">%s</span>
                </div>',
                esc_html(number_format($rating, 1, ',', '')),
                $star_svg
            );
        }

        if ($count) {
            $output .= sprintf(
                '<div class="viva-ugc-review-count">(%s %s)</div>',
                esc_html(number_format($count, 0, ',', ' ')),
                esc_html(_n('review', 'reviews', $count, 'viva-ugc-reviews'))
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render video row with horizontal scroll
     *
     * @param array $videos Array of review items with video data
     * @return string HTML output
     */
    private function render_video_row($videos) {
        if (empty($videos)) {
            return '';
        }

        $output = '<div class="viva-ugc-video-row">';

        foreach ($videos as $index => $review) {
            $video_webp = $review['video_webp'];
            $video_mp4 = $review['video_mp4'];

            if (empty($video_webp) && empty($video_mp4)) {
                continue;
            }

            // First 3 videos load immediately, others lazy-load
            $preload = $index < 3 ? 'metadata' : 'none';

            $output .= '<div class="viva-ugc-video-item">';
            $output .= sprintf(
                '<video autoplay muted loop playsinline preload="%s" aria-label="%s">',
                esc_attr($preload),
                esc_attr($review['customer_name'] . ' - review video')
            );

            // WebP source first (modern browsers)
            if ($video_webp && !empty($video_webp['url'])) {
                $output .= sprintf(
                    '<source src="%s" type="video/webm">',
                    esc_url($video_webp['url'])
                );
            }

            // MP4 fallback
            if ($video_mp4 && !empty($video_mp4['url'])) {
                $output .= sprintf(
                    '<source src="%s" type="video/mp4">',
                    esc_url($video_mp4['url'])
                );
            }

            $output .= '</video>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render photo grid with reviews
     *
     * @param array $reviews Array of review items
     * @return string HTML output
     */
    private function render_photo_grid($reviews) {
        if (empty($reviews)) {
            return '';
        }

        $output = '<div class="viva-ugc-photo-grid">';

        foreach ($reviews as $review) {
            $output .= '<div class="viva-ugc-photo-card">';

            // Image (if available)
            if (!empty($review['image']) && !empty($review['image']['url'])) {
                $image = $review['image'];
                $output .= sprintf(
                    '<img src="%s" alt="%s" class="viva-ugc-photo-image" loading="lazy" width="%d" height="%d">',
                    esc_url($image['url']),
                    esc_attr($review['customer_name'] . ' - opinia klienta'),
                    !empty($image['width']) ? absint($image['width']) : 600,
                    !empty($image['height']) ? absint($image['height']) : 600
                );
            }

            // Content (stars, name, text)
            $output .= '<div class="viva-ugc-photo-content">';

            // Stars
            $output .= $this->render_stars($review['rating']);

            // Customer name
            $output .= sprintf(
                '<div class="viva-ugc-author">%s</div>',
                esc_html($review['customer_name'])
            );

            // Review text
            $output .= sprintf(
                '<div class="viva-ugc-text">%s</div>',
                esc_html($review['review_text'])
            );

            $output .= '</div>'; // .viva-ugc-photo-content
            $output .= '</div>'; // .viva-ugc-photo-card
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render star rating
     *
     * @param int $rating Rating value (1-5)
     * @return string HTML output
     */
    private function render_stars($rating) {
        $star_svg = apply_filters('viva_ugc_star_svg', $this->get_star_svg());

        $output = sprintf(
            '<div class="viva-ugc-stars" aria-label="%s">',
            esc_attr($rating . ' out of 5 stars')
        );

        for ($i = 1; $i <= 5; $i++) {
            $class = $i <= $rating ? 'viva-ugc-star-filled' : 'viva-ugc-star-empty';
            $output .= sprintf(
                '<span class="viva-ugc-star %s">%s</span>',
                esc_attr($class),
                $star_svg
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Get star SVG icon
     *
     * @return string SVG markup
     */
    private function get_star_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    }

    /**
     * Render Omnibus directive disclaimer
     *
     * @return string HTML output
     */
    private function render_omnibus_disclaimer() {
        $text = apply_filters(
            'viva_ugc_omnibus_text',
            'Przedstawione opinie zostały wyselekcjonowane i nie stanowią pełnej listy recenzji produktu. Zdjęcia i filmy są własnością klientów, którzy wyrazili zgodę na ich publikację.'
        );

        return sprintf(
            '<div class="viva-ugc-omnibus">
                <p>%s</p>
            </div>',
            wp_kses_post($text)
        );
    }

    /**
     * Get inline styles for UGC reviews display
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
            /* Mobile-first UGC Reviews styles */
            .viva-ugc-reviews {
                margin: 20px 0;
            }

            /* Overall Rating Header */
            .viva-ugc-overall-rating {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 20px;
                padding-bottom: 16px;
                border-bottom: 1px solid #e0e0e0;
            }

            .viva-ugc-overall-stars {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .viva-ugc-rating-number {
                font-size: 24px;
                font-weight: 700;
                color: #333;
            }

            .viva-ugc-star-icon {
                width: 24px;
                height: 24px;
            }

            .viva-ugc-star-icon svg {
                width: 100%;
                height: 100%;
                fill: #FFD700;
            }

            .viva-ugc-review-count {
                font-size: 14px;
                color: #666;
            }

            /* Video Row - Horizontal Scroll */
            .viva-ugc-video-row {
                display: flex;
                gap: 12px;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 24px;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE/Edge */
            }

            .viva-ugc-video-row::-webkit-scrollbar {
                display: none; /* Chrome/Safari */
            }

            .viva-ugc-video-item {
                flex: 0 0 auto;
                width: 45%; /* Mobile: ~2.2 videos visible */
                aspect-ratio: 9 / 16;
                scroll-snap-align: start;
                border-radius: 8px;
                overflow: hidden;
                background: #f0f0f0;
            }

            .viva-ugc-video-item video {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            /* Photo Grid - 2 Columns Mobile */
            .viva-ugc-photo-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .viva-ugc-photo-card {
                background: #fff;
                border: 1px solid rgba(0,0,0,0.1);
                border-radius: 8px;
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .viva-ugc-photo-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }

            .viva-ugc-photo-image {
                width: 100%;
                aspect-ratio: 1 / 1;
                object-fit: cover;
                display: block;
            }

            .viva-ugc-photo-content {
                padding: 12px;
            }

            /* Star Rating */
            .viva-ugc-stars {
                display: flex;
                gap: 2px;
                margin-bottom: 8px;
            }

            .viva-ugc-star {
                width: 16px;
                height: 16px;
                display: inline-block;
            }

            .viva-ugc-star svg {
                width: 100%;
                height: 100%;
            }

            .viva-ugc-star-filled svg {
                fill: #FFD700;
            }

            .viva-ugc-star-empty svg {
                fill: #e0e0e0;
            }

            /* Customer Name */
            .viva-ugc-author {
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 6px;
                color: #333;
            }

            /* Review Text */
            .viva-ugc-text {
                font-size: 13px;
                color: #555;
                line-height: 1.4;
            }

            /* Omnibus Disclaimer */
            .viva-ugc-omnibus {
                margin-top: 24px;
                padding: 16px;
                background: #f7f7f7;
                border-left: 3px solid #333;
                font-size: 12px;
                color: #666;
                line-height: 1.5;
            }

            .viva-ugc-omnibus p {
                margin: 0;
            }

            /* Tablet (768px+) */
            @media (min-width: 768px) {
                .viva-ugc-rating-number {
                    font-size: 28px;
                }

                .viva-ugc-star-icon {
                    width: 28px;
                    height: 28px;
                }

                .viva-ugc-video-item {
                    width: 32%; /* Tablet: ~3 videos visible */
                }

                .viva-ugc-photo-grid {
                    grid-template-columns: repeat(3, 1fr);
                    gap: 16px;
                }

                .viva-ugc-photo-content {
                    padding: 14px;
                }

                .viva-ugc-author {
                    font-size: 15px;
                }

                .viva-ugc-text {
                    font-size: 14px;
                }
            }

            /* Desktop (1024px+) */
            @media (min-width: 1024px) {
                .viva-ugc-photo-grid {
                    grid-template-columns: repeat(4, 1fr);
                    gap: 20px;
                }

                .viva-ugc-video-item {
                    width: 30%; /* Desktop: ~3.3 videos visible */
                }

                .viva-ugc-photo-content {
                    padding: 16px;
                }
            }
        </style>';
    }

    /**
     * Register ACF field group for UGC Reviews
     * Auto-creates fields so manual ACF setup isn't required
     * Compatible with ACF Free (no repeater field)
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return; // ACF not installed
        }

        // Allow themes/plugins to adjust max review count
        $max_reviews = apply_filters('viva_ugc_reviews_max_count', 10);

        $fields = [];

        // Overall Rating
        $fields[] = [
            'key' => 'field_viva_ugc_overall_rating',
            'label' => __('Overall Rating', 'viva-ugc-reviews'),
            'name' => 'viva_ugc_overall_rating',
            'type' => 'number',
            'instructions' => __('Overall product rating to display (e.g., 4.7). Leave empty to hide.', 'viva-ugc-reviews'),
            'required' => 0,
            'min' => 0,
            'max' => 5,
            'step' => 0.1,
            'placeholder' => '4.7',
            'wrapper' => [
                'width' => '50',
            ],
        ];

        // Total Reviews Count
        $fields[] = [
            'key' => 'field_viva_ugc_total_reviews',
            'label' => __('Total Reviews', 'viva-ugc-reviews'),
            'name' => 'viva_ugc_total_reviews',
            'type' => 'number',
            'instructions' => __('Total number of reviews to display (e.g., 1200). Leave empty to hide.', 'viva-ugc-reviews'),
            'required' => 0,
            'min' => 0,
            'step' => 1,
            'placeholder' => '1200',
            'wrapper' => [
                'width' => '50',
            ],
        ];

        // Generate fields for each review
        for ($i = 1; $i <= $max_reviews; $i++) {
            // Customer Name
            $fields[] = [
                'key' => "field_viva_ugc_review_{$i}_name",
                'label' => sprintf(__('Review %d - Customer Name', 'viva-ugc-reviews'), $i),
                'name' => "viva_ugc_review_{$i}_name",
                'type' => 'text',
                'instructions' => sprintf(__('Customer name for review %d (e.g., "Marta W."). Leave empty to hide this review.', 'viva-ugc-reviews'), $i),
                'required' => 0,
                'maxlength' => 50,
                'placeholder' => 'Marta W.',
                'wrapper' => [
                    'width' => '50',
                ],
            ];

            // Rating
            $fields[] = [
                'key' => "field_viva_ugc_review_{$i}_rating",
                'label' => sprintf(__('Review %d - Rating', 'viva-ugc-reviews'), $i),
                'name' => "viva_ugc_review_{$i}_rating",
                'type' => 'select',
                'instructions' => sprintf(__('Star rating for review %d', 'viva-ugc-reviews'), $i),
                'required' => 0,
                'choices' => [
                    5 => '5 ★★★★★',
                    4 => '4 ★★★★☆',
                    3 => '3 ★★★☆☆',
                    2 => '2 ★★☆☆☆',
                    1 => '1 ★☆☆☆☆',
                ],
                'default_value' => 5,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ];

            // Review Text
            $fields[] = [
                'key' => "field_viva_ugc_review_{$i}_text",
                'label' => sprintf(__('Review %d - Text', 'viva-ugc-reviews'), $i),
                'name' => "viva_ugc_review_{$i}_text",
                'type' => 'textarea',
                'instructions' => sprintf(__('Review text for review %d (max 100 characters)', 'viva-ugc-reviews'), $i),
                'required' => 0,
                'maxlength' => 100,
                'rows' => 3,
                'placeholder' => 'Świetny produkt, polecam!',
                'wrapper' => [
                    'width' => '100',
                ],
            ];

            // Image
            $fields[] = [
                'key' => "field_viva_ugc_review_{$i}_image",
                'label' => sprintf(__('Review %d - Image', 'viva-ugc-reviews'), $i),
                'name' => "viva_ugc_review_{$i}_image",
                'type' => 'image',
                'instructions' => sprintf(__('Upload square image for review %d (1:1 ratio recommended, optional)', 'viva-ugc-reviews'), $i),
                'required' => 0,
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'wrapper' => [
                    'width' => '100',
                ],
            ];

            // Video WebP
            $fields[] = [
                'key' => "field_viva_ugc_review_{$i}_video_webp",
                'label' => sprintf(__('Review %d - Video (WebP/WebM)', 'viva-ugc-reviews'), $i),
                'name' => "viva_ugc_review_{$i}_video_webp",
                'type' => 'file',
                'instructions' => sprintf(__('WebP/WebM video for review %d (9:16 vertical format, optional)', 'viva-ugc-reviews'), $i),
                'required' => 0,
                'return_format' => 'array',
                'library' => 'all',
                'mime_types' => 'webm,video/webm',
                'wrapper' => [
                    'width' => '50',
                ],
            ];

            // Video MP4
            $fields[] = [
                'key' => "field_viva_ugc_review_{$i}_video_mp4",
                'label' => sprintf(__('Review %d - Video (MP4)', 'viva-ugc-reviews'), $i),
                'name' => "viva_ugc_review_{$i}_video_mp4",
                'type' => 'file',
                'instructions' => sprintf(__('MP4 video for review %d (9:16 vertical format, fallback for older browsers, optional)', 'viva-ugc-reviews'), $i),
                'required' => 0,
                'return_format' => 'array',
                'library' => 'all',
                'mime_types' => 'mp4,video/mp4',
                'wrapper' => [
                    'width' => '50',
                ],
            ];
        }

        acf_add_local_field_group([
            'key' => 'group_viva_ugc_reviews',
            'title' => __('Viva UGC Reviews', 'viva-ugc-reviews'),
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
            'menu_order' => 15,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => __('Add curated UGC-style reviews with photos and videos. Display using [product_ugc_reviews] shortcode. Compatible with ACF Free.', 'viva-ugc-reviews'),
        ]);
    }
}

// Initialize plugin
Viva_UGC_Reviews::get_instance();
