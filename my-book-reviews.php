<?php
/**
 * Plugin Name: My Book Reviews App
 * Description: A custom plugin for searching books and submitting/displaying reviews.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly. Prevents direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Your plugin's PHP code will go below this line.

// =======================================================================================
// === 1. PLUGIN ACTIVATION & DEACTIVATION HOOKS (DATABASE TABLE CREATION/DELETION) ===
// =======================================================================================

// Function to create the custom database table on plugin activation
function my_book_reviews_install() {
    global $wpdb; // Access WordPress database object
    $table_name = $wpdb->prefix . 'book_reviews'; // Use WordPress table prefix for uniqueness
    $charset_collate = $wpdb->get_charset_collate(); // Get database character set and collation

    // SQL statement to create the table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        book_title varchar(255) NOT NULL,
        reviewer_name varchar(100) NOT NULL,
        review_text text NOT NULL,
        rating tinyint(1) NOT NULL, -- 1-5 star rating
        review_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        book_openlibrary_id varchar(50), -- To link to Open Library books (optional, but good practice)
        PRIMARY KEY (id)
    ) $charset_collate;"; // Include charset and collation

    // We need to include upgrade.php for the dbDelta function
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql ); // This function creates the table if it doesn't exist or updates it if changed.
}
// Register the activation hook to run the install function when the plugin is activated
register_activation_hook( __FILE__, 'my_book_reviews_install' );

/**
 * Function to remove the custom database table on plugin deactivation (optional, for cleanup).
 * This is useful if you want to completely remove plugin data when it's deactivated.
 */
function my_book_reviews_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'book_reviews';
    // Drop the table if it exists
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}
// Register the deactivation hook
register_deactivation_hook( __FILE__, 'my_book_reviews_uninstall' );


// =======================================================================================
// === 2. AJAX HANDLERS (PHP FUNCTIONS CALLED BY JAVASCRIPT) ===
// =======================================================================================

/**
 * Handles the AJAX request to search for books using the Open Library API.
 * This function will be called by your JavaScript (script.js).
 */
function my_book_reviews_ajax_search_books() {
    // Sanitize and validate the search query from the AJAX request
    $query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';

    if ( empty( $query ) || strlen( $query ) < 3 ) {
        wp_send_json_error( 'Search query must be at least 3 characters.' );
    }

    // Construct the Open Library Search API URL
    $api_url = 'https://openlibrary.org/search.json?q=' . urlencode( $query );

    // Use WordPress's built-in HTTP API to make a request to Open Library
    $response = wp_remote_get( $api_url, array(
        'timeout' => 10, // Timeout after 10 seconds
        'user-agent' => 'MyBookReviewApp/1.0 (info@yourdomain.com)', // Good practice to identify your app
    ) );

    // Check for WordPress HTTP errors
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Failed to connect to Open Library API: ' . $response->get_error_message() );
    }

    // Get the body of the response and decode the JSON
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    // Check if API response is valid and contains 'docs' (search results)
    if ( empty( $data ) || ! isset( $data['docs'] ) ) {
        wp_send_json_error( 'No books found or invalid API response format.' );
    }

    $books = array();
    // Loop through the results and extract relevant information
    foreach ( $data['docs'] as $doc ) {
        // Ensure necessary data exists before adding the book
        if ( isset( $doc['title'] ) && isset( $doc['author_name'] ) && ! empty( $doc['author_name'][0] ) ) {
            $book = array(
                'title'              => esc_html( $doc['title'] ),
                'author'             => esc_html( $doc['author_name'][0] ), // Take the first author
                'first_publish_year' => isset( $doc['first_publish_year'] ) ? esc_html( $doc['first_publish_year'] ) : 'N/A',
                'cover_id'           => isset( $doc['cover_i'] ) ? esc_html( $doc['cover_i'] ) : null, // ID for cover image
                // Open Library uses 'key' as a unique ID for works (e.g., /works/OL12345W)
                'openlibrary_id'     => isset( $doc['key'] ) ? str_replace( '/works/', '', esc_html( $doc['key'] ) ) : null,
            );
            $books[] = $book;
            if ( count( $books ) >= 10 ) { // Limit results to prevent overwhelming the display
                break;
            }
        }
    }

    // Send successful JSON response with book data
    wp_send_json_success( $books );
}
// Hook for logged-in users (wp_ajax_) and non-logged-in users (wp_ajax_nopriv_)
add_action( 'wp_ajax_search_books', 'my_book_reviews_ajax_search_books' );
add_action( 'wp_ajax_nopriv_search_books', 'my_book_reviews_ajax_search_books' );


/**
 * Handles the AJAX request to submit a new book review to the database.
 */
function my_book_reviews_ajax_submit_review() {
    global $wpdb; // Access WordPress database object
    $table_name = $wpdb->prefix . 'book_reviews'; // Your custom table name

    // Retrieve and sanitize data from the AJAX request (assuming JSON payload from JS)
    $input_data = file_get_contents('php://input'); // Get raw POST data
    $data = json_decode($input_data, true); // Decode JSON into an associative array

    // Basic validation for required fields
    if ( ! isset( $data['book_title'], $data['reviewer_name'], $data['review_text'], $data['rating'], $data['book_openlibrary_id'] ) ) {
        wp_send_json_error( 'Missing required fields for review submission.' );
    }

    $book_title          = sanitize_text_field( $data['book_title'] );
    $reviewer_name       = sanitize_text_field( $data['reviewer_name'] );
    $review_text         = sanitize_textarea_field( $data['review_text'] ); // Use for textarea content
    $rating              = intval( $data['rating'] ); // Ensure rating is an integer
    $book_openlibrary_id = sanitize_text_field( $data['book_openlibrary_id'] );

    // More robust validation
    if ( empty( $book_title ) || empty( $reviewer_name ) || empty( $review_text ) || $rating < 1 || $rating > 5 ) {
        wp_send_json_error( 'Please fill in all required fields correctly (rating must be 1-5).' );
    }

    // Insert data into the database using $wpdb->insert for security and ease
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'book_title'          => $book_title,
            'reviewer_name'       => $reviewer_name,
            'review_text'         => $review_text,
            'rating'              => $rating,
            'book_openlibrary_id' => $book_openlibrary_id,
            'review_date'         => current_time( 'mysql' ), // WordPress function for current time
        ),
        array(
            '%s', // Format for book_title (string)
            '%s', // Format for reviewer_name (string)
            '%s', // Format for review_text (string)
            '%d', // Format for rating (integer)
            '%s', // Format for book_openlibrary_id (string)
            '%s', // Format for review_date (datetime string)
        )
    );

    if ( $inserted ) {
        wp_send_json_success( 'Review submitted successfully!' );
    } else {
        wp_send_json_error( 'Failed to submit review. Database error: ' . $wpdb->last_error );
    }
}
add_action( 'wp_ajax_submit_review', 'my_book_reviews_ajax_submit_review' );
add_action( 'wp_ajax_nopriv_submit_review', 'my_book_reviews_ajax_submit_review' );


/**
 * Handles the AJAX request to fetch and display all existing book reviews.
 */
function my_book_reviews_ajax_get_reviews() {
    global $wpdb; // Access WordPress database object
    $table_name = $wpdb->prefix . 'book_reviews'; // Your custom table name

    // Retrieve reviews, ordered by date descending
    // Using $wpdb->get_results for safe query and result retrieval
    $reviews = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY review_date DESC", ARRAY_A ); // ARRAY_A returns associative array

    if ( $reviews ) {
        wp_send_json_success( $reviews );
    } else {
        // If no reviews found, still return success but with empty data
        wp_send_json_success( array() );
    }
}
add_action( 'wp_ajax_get_reviews', 'my_book_reviews_ajax_get_reviews' );
add_action( 'wp_ajax_nopriv_get_reviews', 'my_book_reviews_ajax_get_reviews' );


// =======================================================================================
// === 3. SHORTCODES (HTML FORM & DISPLAY VIA WORDPRESS PAGES) ===
// =======================================================================================

/**
 * Shortcode to display the book search form.
 * Users will add [book_search_form] to a WordPress page.
 */
function my_book_reviews_search_form_shortcode() {
    ob_start(); // Start output buffering
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
    return ob_get_clean(); // Return the buffered HTML
}
add_shortcode( 'book_search_form', 'my_book_reviews_search_form_shortcode' );

/**
 * Shortcode to display the book review submission form.
 * Users will add [book_review_submission_form] to a WordPress page.
 */
function my_book_reviews_submission_form_shortcode() {
    ob_start();
    ?>
    <div class="book-review-form-container section">
        <h3>Submit a Review</h3>
        <form id="review-form">
            <div class="form-group">
                <label for="book_title">Book Title:</label>
                <!-- Readonly input for book title, populated by JS -->
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
            <!-- Hidden field for Open Library ID, will be populated by JS -->
            <input type="hidden" id="book_openlibrary_id" name="book_openlibrary_id">

            <button type="submit" class="btn submit-btn">Submit Review</button>
        </form>
        <div id="form-message" class="message"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'book_review_submission_form', 'my_book_reviews_submission_form_shortcode' );


/**
 * Shortcode to display all existing book reviews.
 * Users will add [display_all_book_reviews] to a WordPress page.
 */
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


// =======================================================================================
// === 4. ENQUEUE SCRIPTS & STYLES (JAVASCRIPT & CSS) ===
// =======================================================================================

/**
 * Enqueues custom JavaScript and CSS files for the frontend.
 * This is the proper WordPress way to load scripts and styles.
 */
function my_book_reviews_enqueue_assets() {
    // Enqueue jQuery as a dependency (WordPress includes it by default)
    wp_enqueue_script( 'jquery' );

    // Enqueue your custom JavaScript file
    wp_enqueue_script(
        'my-book-reviews-script',                               // Unique handle for your script
        plugin_dir_url( __FILE__ ) . 'js/script.js',           // Path to your script file
        array( 'jquery' ),                                     // Dependencies (this script needs jQuery)
        '1.0',                                                 // Version number
        true                                                   // Load in footer
    );

    // Pass PHP variables (like the AJAX URL) to your JavaScript file
    wp_localize_script(
        'my-book-reviews-script', // Handle of the script to attach data to
        'myBookReviewsData',      // JavaScript object name (e.g., myBookReviewsData.ajaxurl)
        array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ), // WordPress AJAX endpoint URL
        )
    );

    // Enqueue your custom CSS file
    wp_enqueue_style(
        'my-book-reviews-style',                               // Unique handle for your stylesheet
        plugin_dir_url( __FILE__ ) . 'css/style.css',          // Path to your stylesheet
        array(),                                               // No dependencies for CSS
        '1.0',                                                 // Version number
        'all'                                                  // Media type (all devices)
    );
}
// Hook into wp_enqueue_scripts to load assets on the frontend
add_action( 'wp_enqueue_scripts', 'my_book_reviews_enqueue_assets' );

?>