jQuery(document).ready(function($) {
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded. Plugin cannot function.');
        return;
    }

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
    const $darkModeToggle = $('#dark-mode-toggle');
    const $cursorStyleSelect = $('#cursor-style');
    const $highlightColorInput = $('#highlight-color');

    if (typeof myBookReviewsData === 'undefined' || !myBookReviewsData.ajaxurl || !myBookReviewsData.nonce) {
        console.error('myBookReviewsData is undefined or missing properties. Ensure wp_localize_script is set up correctly.');
        if ($searchResultsDiv.length) {
            $searchResultsDiv.html('<p class="message error">Plugin initialization failed. Please contact the site administrator.</p>');
        }
        return;
    }

    const ajaxurl = myBookReviewsData.ajaxurl;
    const nonce = myBookReviewsData.nonce;

    const applyTheme = (theme) => {
        $('body').toggleClass('dark-mode', theme === 'dark');
        localStorage.setItem('theme', theme);
    };

    const applyCursor = (cursor) => {
        $('body').css('cursor', cursor);
        localStorage.setItem('cursorStyle', cursor);
    };

    const applyHighlight = (color) => {
        let $styleEl = $('#custom-highlight-style');
        if (!$styleEl.length) {
            $styleEl = $('<style id="custom-highlight-style"></style>').appendTo('head');
        }
        $styleEl.text(`
            .container ::selection { background: ${color}; }
            .container ::-moz-selection { background: ${color}; }
        `);
        localStorage.setItem('highlightColor', color);
    };

    if ($darkModeToggle.length) {
        const savedTheme = localStorage.getItem('theme') || 'light';
        applyTheme(savedTheme);
        $darkModeToggle.on('click', () => {
            const currentTheme = $('body').hasClass('dark-mode') ? 'dark' : 'light';
            applyTheme(currentTheme === 'light' ? 'dark' : 'light');
        });
    }

    if ($cursorStyleSelect.length) {
        const savedCursor = localStorage.getItem('cursorStyle');
        if (savedCursor) {
            $cursorStyleSelect.val(savedCursor);
            applyCursor(savedCursor);
        }
        $cursorStyleSelect.on('change', (event) => {
            applyCursor($(event.target).val());
        });
    }

    if ($highlightColorInput.length) {
        const savedHighlight = localStorage.getItem('highlightColor');
        if (savedHighlight) {
            $highlightColorInput.val(savedHighlight);
            applyHighlight(savedHighlight);
        }
        $highlightColorInput.on('input', (event) => {
            applyHighlight($(event.target).val());
        });
    }

    const speakText = (text) => {
        if ('speechSynthesis' in window) {
            if (speechSynthesis.speaking) {
                speechSynthesis.cancel();
            }
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'en-US';
            speechSynthesis.speak(utterance);
        } else {
            if ($reviewsDisplayDiv.length) {
                $reviewsDisplayDiv.prepend('<p class="message error">Your browser does not support text-to-speech.</p>');
                setTimeout(() => {
                    $reviewsDisplayDiv.find('.message.error').fadeOut();
                }, 5000);
            }
        }
    };

    const displayMessage = (message, type = 'success') => {
        if ($formMessageDiv.length) {
            $formMessageDiv.text(message).removeClass().addClass(`message ${type}`).show();
            setTimeout(() => {
                $formMessageDiv.fadeOut();
            }, 5000);
        } else {
            console.warn('Form message div (#form-message) not found. Message:', message);
        }
    };

    const debounce = (func, delay) => {
        let timeoutId;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(context, args), delay);
        };
    };

    const performSearch = () => {
        if (!$bookSearchInput.length || !$searchResultsDiv.length) {
            console.error('Search input or results div not found.');
            return;
        }

        const query = $bookSearchInput.val().trim();
        if (query.length < 3) {
            $searchResultsDiv.html('<p class="message error">Please enter at least 3 characters to search.</p>');
            return;
        }

        $searchResultsDiv.html('<p>Searching for books...</p>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'search_books',
                nonce: nonce,
                query: query
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && Array.isArray(response.data) && response.data.length > 0) {
                        let booksHtml = '<h4 style="text-align: center;">Search Results:</h4><div class="search-results-grid">';
                        response.data.forEach(book => {
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
                    $searchResultsDiv.html(`<p class="message error">Error: ${response.data.message || 'Unknown error'}</p>`);
                }
            },
            error: function(xhr, status, error) {
                $searchResultsDiv.html(`<p class="message error">Search error: ${error}</p>`);
                console.error('Search AJAX Error:', error, xhr);
            }
        });
    };

    if ($bookSearchButton.length) {
        $bookSearchButton.on('click', performSearch);
    } else {
        console.warn('Search button (#book-search-button) not found.');
    }

    if ($bookSearchInput.length) {
        $bookSearchInput.on('keyup', debounce(performSearch, 500));
    } else {
        console.warn('Search input (#book-search-input) not found.');
    }

    if ($searchResultsDiv.length) {
        $searchResultsDiv.on('click', '.select-book-btn', function() {
            const $bookCard = $(this).closest('.book-card');
            const openLibraryId = $bookCard.data('openlibrary-id') || '';
            const title = $bookCard.data('title') || '';

            if ($bookTitleInput.length && $bookOpenLibraryIdInput.length) {
                $bookTitleInput.val(title);
                $bookOpenLibraryIdInput.val(openLibraryId);

                $('html, body').animate({
                    scrollTop: $bookTitleInput.offset().top - 100
                }, 500);

                displayMessage('Book selected! Please fill out the review form below.', 'success');
                $searchResultsDiv.html('');
                $bookSearchInput.val('');
            } else {
                console.error('Book title or Open Library ID input not found.');
            }
        });
    } else {
        console.warn('Search results div (#search-results) not found.');
    }

    if ($reviewForm.length) {
        $reviewForm.on('submit', function(event) {
            event.preventDefault();

            const reviewData = {
                action: 'submit_review',
                nonce: nonce,
                book_title: $bookTitleInput.length ? $bookTitleInput.val() : '',
                reviewer_name: $reviewerNameInput.length ? $reviewerNameInput.val() : '',
                review_text: $reviewTextInput.length ? $reviewTextInput.val() : '',
                rating: $ratingInput.length ? $ratingInput.val() : '',
                book_openlibrary_id: $bookOpenLibraryIdInput.length ? $bookOpenLibraryIdInput.val() : ''
            };

            if (!reviewData.book_title || !reviewData.reviewer_name || !reviewData.review_text || reviewData.rating < 1 || reviewData.rating > 5) {
                displayMessage('Please fill in all required fields and provide a valid rating (1-5).', 'error');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: reviewData,
                success: function(response) {
                    if (response.success) {
                        displayMessage(response.data.message || 'Review submitted successfully!', 'success');
                        $reviewForm[0].reset();
                        if ($bookTitleInput.length) {
                            $bookTitleInput.val('');
                        }
                        if ($bookOpenLibraryIdInput.length) {
                            $bookOpenLibraryIdInput.val('');
                        }
                        fetchAndDisplayReviews();
                    } else {
                        displayMessage(`Error: ${response.data.message || 'Unknown error'}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    displayMessage(`Submission error: ${error}`, 'error');
                    console.error('Review Submission AJAX Error:', error, xhr);
                }
            });
        });
    } else {
        console.warn('Review form (#review-form) not found.');
    }

    const fetchAndDisplayReviews = () => {
        if (!$reviewsDisplayDiv.length) {
            console.error('Reviews display div (#reviews-display) not found.');
            return;
        }

        $reviewsDisplayDiv.html('<p>Loading reviews...</p>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_reviews',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && Array.isArray(response.data) && response.data.length > 0) {
                        let reviewsHtml = '';
                        response.data.forEach(review => {
                            reviewsHtml += `
                                <div class="book-review-item">
                                    <h4>${review.book_title || 'Unknown Title'}</h4>
                                    <p><strong>Reviewer:</strong> ${review.reviewer_name || 'Anonymous'}</p>
                                    <p><strong>Rating:</strong> ${'⭐'.repeat(parseInt(review.rating) || 0)}</p>
                                    <p id="review-text-${review.id || Date.now()}">${review.review_text || 'No review text'}</p>
                                    <small>Reviewed on: ${new Date(review.review_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</small>
                                    <button class="btn read-aloud-button" data-text-id="review-text-${review.id || Date.now()}">Listen to Review</button>
                                </div>
                            `;
                        });
                        $reviewsDisplayDiv.html(reviewsHtml);
                    } else {
                        $reviewsDisplayDiv.html('<p>No reviews yet. Be the first to add one!</p>');
                    }
                } else {
                    $reviewsDisplayDiv.html(`<p class="message error">Error: ${response.data.message || 'Unknown error'}</p>`);
                }
            },
            error: function(xhr, status, error) {
                $reviewsDisplayDiv.html(`<p class="message error">Error loading reviews: ${error}</p>`);
                console.error('Fetch Reviews AJAX Error:', error, xhr);
            }
        });
    };

    if ($reviewsDisplayDiv.length) {
        $reviewsDisplayDiv.on('click', '.read-aloud-button', function() {
            const textId = $(this).data('text-id');
            const textElement = document.getElementById(textId);
            if (textElement) {
                speakText(textElement.textContent);
            } else {
                console.error(`Text element with ID ${textId} not found.`);
            }
        });
    }

    fetchAndDisplayReviews();
});
