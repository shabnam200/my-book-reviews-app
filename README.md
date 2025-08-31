My Book Reviews App

This is a custom WordPress plugin that allows users to search for books using the Open Library API, submit reviews, and display all reviews on a single page.

🚀 Features

Book Search: Search for books by title or author using a powerful, real-time AJAX search.

Review Submission: Users can submit a rating and a written review for a specific book.

Review Display: All submitted reviews are stored in the database and displayed on a separate section of the page.

Database Integration: Creates a custom database table upon activation to securely store review data.

Shortcode-Based: Easily add the search, submission, and display sections to any WordPress page using shortcodes.

📦 Installation

Download the plugin:

Click the "Code" button on this repository page and select "Download ZIP."

Unzip the my-book-reviews-app-main folder.

Upload to WordPress:

Log in to your WordPress dashboard.

Navigate to Plugins > Add New.

Click Upload Plugin, then select the zipped file.

Alternatively, upload the unzipped my-book-reviews-app folder via FTP to your wp-content/plugins/ directory.

Activate the plugin:

Go to Plugins > Installed Plugins.

Find "My Book Reviews App" in the list and click "Activate."

📝 Usage

After activating the plugin, you can add its features to any page or post using the following shortcodes:

[book_search_form] - Displays the search bar and results section.

[book_review_submission_form] - Displays the form for submitting a review.

[display_all_book_reviews] - Displays all reviews submitted to the site.

Example Page Setup:
Create a new page and add the shortcodes like this:

[book_search_form]

[book_review_submission_form]

[display_all_book_reviews]


🤝 Contributing

If you find any bugs or have suggestions for new features, please open an issue or submit a pull request.
