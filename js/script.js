document.addEventListener('DOMContentLoaded', () => {
    // Ensure jQuery is loaded before using it
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded. This script requires jQuery.');
        return;
    }

    const $ = jQuery; // Use $ alias for jQuery

    // === DOM Elements ===
    const $darkModeToggle = $('#dark-mode-toggle');
    const $cursorStyleSelect = $('#cursor-style');
    const $highlightColorInput = $('#highlight-color');
    const $bookSearchInput = $('#book-search-input');
    const $bookSearchButton = $('#book-search-button');
    const $searchResultsDiv = $('#search-results');
    const $reviewForm = $('#review-form');
    const $bookTitleInput = $('#book_title');
    const $bookOpenLibraryIdInput = $('#book_openlibrary_id');
    const $reviewerNameInput = $('#reviewer_name');
    const $ratingInput = $('#rating');
    const $reviewTextInput = $('#review_text');
    const $formMessageDiv = $('#form-message');
    const $reviewsDisplayDiv = $('#reviews-display');

    // === WordPress AJAX Endpoint ===
    // This variable is passed from PHP using wp_localize_script
    const ajaxurl = myBookReviewsData.ajaxurl;

    // === Customization / Plugin Functions ===

    // Dark Mode Toggle
    const applyTheme = (theme) => {
        $('body').toggleClass('dark-mode', theme === 'dark');
        localStorage.setItem('theme', theme);
    };

    // Load saved theme on page load
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    $darkModeToggle.on('click', () => {
        const currentTheme = $('body').hasClass('dark-mode') ? 'dark' : 'light';
        applyTheme(currentTheme === 'light' ? 'dark' : 'light');
    });

    // Custom Cursor & Highlight
    const applyCursor = (cursor) => {
        $('body').css('cursor', cursor);
        localStorage.setItem('cursorStyle', cursor);
    };

    const applyHighlight = (color) => {
        let $styleEl = $('#custom-highlight-style');
        if (!$styleEl.length) {
            $styleEl = $('<style id="custom-highlight-style"></style>').appendTo('head');
        }
        // Use ::selection for modern browsers, ::-moz-selection for Firefox
        $styleEl.text(`::selection { background: ${color}; }::-moz-selection { background: ${color}; }`);
        localStorage.setItem('highlightColor', color);
    };

    // Load saved cursor and highlight on page load
    const savedCursor = localStorage.getItem('cursorStyle');
    const savedHighlight = localStorage.getItem('highlightColor');

    if (savedCursor) {
        $cursorStyleSelect.val(savedCursor);
        applyCursor(savedCursor);
    }
    if (savedHighlight) {
        $highlightColorInput.val(savedHighlight);
        applyHighlight(savedHighlight);
    }

    $cursorStyleSelect.on('change', (event) => {
        applyCursor($(event.target).val());
    });

    $highlightColorInput.on('input', (event) => {
        applyHighlight($(event.target).val());
    });

    // Read Aloud Function (Dynamic buttons will be added for reviews)
    const speakText = (text) => {
        if ('speechSynthesis' in window) {
            // Stop any ongoing speech
            if (speechSynthesis.speaking) {
                speechSynthesis.cancel();
            }
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'en-US'; // Set language, can be dynamic if needed
            speechSynthesis.speak(utterance);
        } else {
            displayMessage('Your browser does not support text-to-speech.', 'error');
        }
    };

    // Function to display messages in the UI
    const displayMessage = (message, type = 'success') => {
        $formMessageDiv.text(message);
        $formMessageDiv.removeClass().addClass(`message ${type}`).show();
        setTimeout(() => {
            $formMessageDiv.fadeOut(); // Fade out the message
        }, 5000); // Hide after 5 seconds
    };

    // === Book Search Logic ===
    $bookSearchButton.on('click', async () => {
        const query = $bookSearchInput.val().trim();
        if (query.length < 3) {
            $searchResultsDiv.html('<p class="message error">Please enter at least 3 characters to search.</p>');
            return;
        }

        $searchResultsDiv.html('<p>Searching for books...</p>');

        try {
            // Send AJAX request to WordPress AJAX endpoint
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                // 'action' parameter tells WordPress which PHP function to call
                body: `action=search_books&query=${encodeURIComponent(query)}`
            });
            const result = await response.json(); // Parse JSON response

            if (result.success) {
                if (result.data && result.data.length > 0) {
                    let booksHtml = '<h4 style="text-align: center;">Search Results:</h4><div class="search-results-grid">';
                    result.data.forEach(book => {
                        const coverUrl = book.cover_id ? `https://covers.openlibrary.org/b/id/${book.cover_id}-M.jpg` : `https://placehold.co/128x193/cccccc/333333?text=No+Cover`;
                        booksHtml += `
                            <div class="book-card"
                                data-openlibrary-id="${book.openlibrary_id || ''}"
                                data-title="${book.title || ''}"
                                data-author="${book.author || ''}">
                                <img src="${coverUrl}" alt="Book Cover" class="book-cover">
                                <h5>${book.title || 'N/A Title'}</h5>
                                <p>by ${book.author || 'N/A Author'}</p>
                                <p>Published: ${book.first_publish_year || 'N/A'}</p>
                                <button class="btn select-book-btn">Select Book</button>
                            </div>
                        `;
                    });
                    booksHtml += '</div>';
                    $searchResultsDiv.html(booksHtml);
                } else {
                    $searchResultsDiv.html('<p>No books found for your search.</p>');
                }
            } else {
                $searchResultsDiv.html(`<p class="message error">Error: ${result.data || result.message}</p>`);
            }
        } catch (error) {
            $searchResultsDiv.html(`<p class="message error">An error occurred during search: ${error.message}</p>`);
            console.error('Search Fetch Error:', error);
        }
    });

    // Event listener for "Select Book" buttons (delegated as they are added dynamically)
    $searchResultsDiv.on('click', '.select-book-btn', (event) => {
        const $bookCard = $(event.target).closest('.book-card');
        const openLibraryId = $bookCard.data('openlibrary-id');
        const title = $bookCard.data('title');

        $bookTitleInput.val(title); // Populate book title in form
        $bookOpenLibraryIdInput.val(openLibraryId); // Populate hidden Open Library ID

        // Optionally, scroll to the review form for better UX
        $('html, body').animate({
            scrollTop: $bookTitleInput.offset().top - 100 // Adjust offset as needed
        }, 500);

        displayMessage('Book selected! Please fill out the review form below.', 'success');
        $searchResultsDiv.html(''); // Clear search results after selection
        $bookSearchInput.val(''); // Clear search input
    });

    // === Review Submission Logic ===
    $reviewForm.on('submit', async (event) => {
        event.preventDefault(); // Prevent default form submission

        // Collect form data manually or using FormData for simpler objects
        const reviewData = {
            action: 'submit_review', // WordPress AJAX action
            book_title: $bookTitleInput.val(),
            reviewer_name: $reviewerNameInput.val(),
            review_text: $reviewTextInput.val(),
            rating: $ratingInput.val(),
            book_openlibrary_id: $bookOpenLibraryIdInput.val()
        };

        // Basic client-side validation
        if (!reviewData.book_title || !reviewData.reviewer_name || !reviewData.review_text || reviewData.rating < 1 || reviewData.rating > 5) {
            displayMessage('Please fill in all required fields and provide a valid rating (1-5).', 'error');
            return;
        }

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', // Send as JSON for PHP to decode
                },
                body: JSON.stringify(reviewData) // Convert object to JSON string
            });
            const result = await response.json(); // Parse JSON response

            if (result.success) {
                displayMessage('Review submitted successfully!', 'success');
                $reviewForm[0].reset(); // Reset the form using native DOM method
                $bookTitleInput.val(''); // Clear pre-filled title
                $bookOpenLibraryIdInput.val(''); // Clear hidden ID
                fetchAndDisplayReviews(); // Refresh the list of reviews
            } else {
                displayMessage(`Error: ${result.data || result.message}`, 'error');
            }
        } catch (error) {
            displayMessage(`An unexpected error occurred during submission: ${error.message}`, 'error');
            console.error('Review Submission Fetch Error:', error);
        }
    });

    // === Fetch and Display Reviews Logic ===
    const fetchAndDisplayReviews = async () => {
        $reviewsDisplayDiv.html('<p>Loading reviews...</p>'); // Show loading state

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_reviews' // WordPress AJAX action
            });
            const result = await response.json();

            if (result.success) {
                if (result.data && result.data.length > 0) {
                    let reviewsHtml = '';
                    result.data.forEach(review => {
                        // Using jQuery for safer attribute setting, although template literals are fine here
                        reviewsHtml += `
                            <div class="book-review-item">
                                <h4>${review.book_title}</h4>
                                <p><strong>Reviewer:</strong> ${review.reviewer_name}</p>
                                <p><strong>Rating:</strong> ${'⭐'.repeat(review.rating)}</p>
                                <p id="review-text-${review.id}">${review.review_text}</p>
                                <small>Reviewed on: ${new Date(review.review_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</small>
                                <button class="btn read-aloud-button" data-text-id="review-text-${review.id}">Listen to Review</button>
                            </div>
                        `;
                    });
                    $reviewsDisplayDiv.html(reviewsHtml);
                } else {
                    $reviewsDisplayDiv.html('<p>No reviews yet. Be the first to add one!</p>');
                }
            } else {
                $reviewsDisplayDiv.html(`<p class="message error">Error loading reviews: ${result.data || result.message}</p>`);
            }
        } catch (error) {
            $reviewsDisplayDiv.html(`<p class="message error">An unexpected error occurred loading reviews: ${error.message}</p>`);
            console.error('Fetch Reviews Error:', error);
        }
    };

    // Event listener for Read Aloud buttons (delegated as they are added dynamically)
    $reviewsDisplayDiv.on('click', '.read-aloud-button', (event) => {
        const textId = $(event.target).data('text-id');
        const textElement = document.getElementById(textId);
        if (textElement) {
            speakText(textElement.textContent);
        }
    });

    // Initial load of reviews when the page loads
    fetchAndDisplayReviews();
});