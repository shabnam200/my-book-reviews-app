<?php
/**
 * Plugin Name: My Book Reviews App
 * Description: A custom plugin for searching books and submitting/displaying reviews.
 * Version: 1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function my_book_reviews_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        book_title varchar(255) NOT NULL,
        reviewer_name varchar(100) NOT NULL,
        review_text text NOT NULL,
        rating tinyint(1) NOT NULL,
        review_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        book_openlibrary_id varchar(50),
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    if ( $wpdb->last_error ) {
        error_log( 'My Book Reviews: Table creation failed - ' . $wpdb->last_error );
    }
}
register_activation_hook( __FILE__, 'my_book_reviews_install' );

function my_book_reviews_deactivate() {
    
}
register_deactivation_hook( __FILE__, 'my_book_reviews_deactivate' );

function my_book_reviews_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}
register_uninstall_hook( __FILE__, 'my_book_reviews_uninstall' );


function my_book_reviews_ajax_search_books() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'my_book_reviews_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
    }

    $query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';

    if ( empty( $query ) || strlen( $query ) < 3 ) {
        wp_send_json_error( array( 'message' => 'Search query must be at least 3 characters.' ) );
    }

    $api_url = 'https://openlibrary.org/search.json?q=' . urlencode( $query );

    $response = wp_remote_get( $api_url, array(
        'timeout' => 10,
        'user-agent' => 'MyBookReviewApp/1.2 (info@yourdomain.com)',
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'Failed to connect to Open Library API: ' . $response->get_error_message() ) );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( empty( $data ) || ! isset( $data['docs'] ) ) {
        wp_send_json_error( array( 'message' => 'No books found or invalid API response.' ) );
    }

    $books = array();
    foreach ( $data['docs'] as $doc ) {
        if ( isset( $doc['title'] ) && isset( $doc['author_name'] ) && ! empty( $doc['author_name'][0] ) ) {
            $books[] = array(
                'title'              => esc_html( $doc['title'] ),
                'author'             => esc_html( $doc['author_name'][0] ),
                'first_publish_year' => isset( $doc['first_publish_year'] ) ? esc_html( $doc['first_publish_year'] ) : 'N/A',
                'cover_id'           => isset( $doc['cover_i'] ) ? esc_html( $doc['cover_i'] ) : null,
                'openlibrary_id'     => isset( $doc['key'] ) ? str_replace( '/works/', '', esc_html( $doc['key'] ) ) : null,
            );
            if ( count( $books ) >= 10 ) {
                break;
            }
        }
    }

    wp_send_json_success( $books );
}
add_action( 'wp_ajax_search_books', 'my_book_reviews_ajax_search_books' );
add_action( 'wp_ajax_nopriv_search_books', 'my_book_reviews_ajax_search_books' );

function my_book_reviews_ajax_submit_review() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'my_book_reviews_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';

    $data = $_POST; 
    if ( ! isset( $data['book_title'], $data['reviewer_name'], $data['review_text'], $data['rating'], $data['book_openlibrary_id'] ) ) {
        wp_send_json_error( array( 'message' => 'Missing required fields for review submission.' ) );
    }

    $book_title          = sanitize_text_field( $data['book_title'] );
    $reviewer_name       = sanitize_text_field( $data['reviewer_name'] );
    $review_text         = sanitize_textarea_field( $data['review_text'] );
    $rating              = intval( $data['rating'] );
    $book_openlibrary_id = sanitize_text_field( $data['book_openlibrary_id'] );

    if ( empty( $book_title ) || empty( $reviewer_name ) || empty( $review_text ) || $rating < 1 || $rating > 5 ) {
        wp_send_json_error( array( 'message' => 'Please fill in all required fields correctly (rating must be 1-5).' ) );
    }

    $inserted = $wpdb->insert(
        $table_name,
        array(
            'book_title'          => $book_title,
            'reviewer_name'       => $reviewer_name,
            'review_text'         => $review_text,
            'rating'              => $rating,
            'book_openlibrary_id' => $book_openlibrary_id,
            'review_date'         => current_time( 'mysql' ),
        ),
        array( '%s', '%s', '%s', '%d', '%s', '%s' )
    );

    if ( $inserted ) {
        wp_send_json_success( array( 'message' => 'Review submitted successfully!' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to submit review. Database error: ' . $wpdb->last_error ) );
    }
}
add_action( 'wp_ajax_submit_review', 'my_book_reviews_ajax_submit_review' );
add_action( 'wp_ajax_nopriv_submit_review', 'my_book_reviews_ajax_submit_review' );

function my_book_reviews_ajax_get_reviews() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'my_book_reviews_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';

    $reviews = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY review_date DESC", ARRAY_A );

    if ( $wpdb->last_error ) {
        wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
    }

    wp_send_json_success( $reviews ?: array() );
}
add_action( 'wp_ajax_get_reviews', 'my_book_reviews_ajax_get_reviews' );
add_action( 'wp_ajax_nopriv_get_reviews', 'my_book_reviews_ajax_get_reviews' );


function my_book_reviews_search_form_shortcode() {
    ob_start();
    ?>
    <div class="book-search-container section">
        <h3>Search for Books</h3>
        <div class="search-input-group">
            <input type="text" id="book-search-input" placeholder="Enter book title or author..." class="input-field">
            <button id="book-search-button" class="btn">Search</button>
        </div>
        <div id="search-results" class="search-results">
            <p>Type a book title or author and click 'Search' to find books!</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'book_search_form', 'my_book_reviews_search_form_shortcode' );

function my_book_reviews_submission_form_shortcode() {
    ob_start();
    ?>
    <div class="book-review-form-container section">
        <h3>Submit a Review</h3>
        <form id="review-form">
            <div class="form-group">
                <label for="book_title">Book Title:</label>
                <input type="text" id="book_title" name="book_title" class="input-field" readonly required placeholder="Select a book from search results">
            </div>
            <div class="form-group">
                <label for="reviewer_name">Your Name:</label>
                <input type="text" id="reviewer_name" name="reviewer_name" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="rating">Rating (1-5 Stars):</label>
                <input type="number" id="rating" name="rating" min="1" max="5" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="review_text">Your Review:</label>
                <textarea id="review_text" name="review_text" rows="5" class="input-field" required></textarea>
            </div>
            <input type="hidden" id="book_openlibrary_id" name="book_openlibrary_id">
            <button type="submit" class="btn submit-btn">Submit Review</button>
        </form>
        <div id="form-message" class="message"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'book_review_submission_form', 'my_book_reviews_submission_form_shortcode' );

function my_book_reviews_display_all_reviews_shortcode() {
    ob_start();
    ?>
    <div class="book-reviews-list section">
        <h3>Recent Reviews</h3>
        <div id="reviews-display">
            <p>Loading reviews...</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'display_all_book_reviews', 'my_book_reviews_display_all_reviews_shortcode' );

function my_book_reviews_customization_controls_shortcode() {
    ob_start();
    ?>
    <div class="custom-style-controls section">
        <div class="control-group">
            <label for="dark-mode-toggle">Toggle Dark Mode:</label>
            <button id="dark-mode-toggle" class="btn">Toggle Dark Mode</button>
        </div>
        <div class="control-group">
            <label for="cursor-style">Cursor Style:</label>
            <select id="cursor-style" class="input-field">
                <option value="default">Default</option>
                <option value="pointer">Pointer</option>
                <option value="crosshair">Crosshair</option>
                <option value="text">Text</option>
            </select>
        </div>
        <div class="control-group">
            <label for="highlight-color">Highlight Color:</label>
            <input type="color" id="highlight-color" class="input-field" value="#007bff">
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'book_reviews_customization', 'my_book_reviews_customization_controls_shortcode' );

function my_book_reviews_enqueue_assets() {
    wp_enqueue_script( 'jquery' );

    wp_enqueue_script(
        'my-book-reviews-script',
        plugin_dir_url( __FILE__ ) . 'js/script.js',
        array( 'jquery' ),
        '1.2',
        true
    );

    wp_localize_script(
        'my-book-reviews-script',
        'myBookReviewsData',
        array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'my_book_reviews_nonce' ),
        )
    );

    wp_enqueue_style(
        'my-book-reviews-style',
        plugin_dir_url( __FILE__ ) . 'css/style.css',
        array(),
        '1.2',
        'all'
    );
}
add_action( 'wp_enqueue_scripts', 'my_book_reviews_enqueue_assets' );

?>
