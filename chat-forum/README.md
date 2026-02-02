# Chat Forum Project

## Overview
This project is a chat forum that includes an accessible blog page and a private chat function between users. It is built using HTML, CSS, and PHP, providing a platform for users to interact through blog posts and private messaging.

## Project Structure
The project is organized into several directories and files, each serving a specific purpose:

- **public/**: Contains the main entry point and public-facing files.
  - `index.php`: Main entry point for the application.
  - `blog.php`: Displays the blog page.
  - `chat.php`: Provides the chat interface.
  - `login.php`: User login form.
  - `register.php`: User registration form.
  - **css/**: Contains stylesheets.
    - `styles.css`: CSS styles for the application.
  - **js/**: Contains JavaScript files.
    - `app.js`: Client-side functionality.

- **src/**: Contains the application logic.
  - **controllers/**: Handles requests and business logic.
    - `AuthController.php`: User authentication and session management.
    - `BlogController.php`: Manages blog posts.
    - `ChatController.php`: Handles chat messages.
  - **models/**: Represents data structures.
    - `User.php`: User entity.
    - `Post.php`: Blog post entity.
    - `Message.php`: Chat message entity.
  - **views/**: Contains templates for rendering content.
    - **layouts/**: Common layout templates.
      - `main.php`: Main layout template.
    - **blog/**: Blog-related views.
      - `index.php`: Displays list of blog posts.
    - **chat/**: Chat-related views.
      - `private.php`: Private chat interface.

- **config/**: Configuration settings for the application.
  - `config.php`: Database connection details.

- **scripts/**: SQL scripts for database setup.
  - `migrate.sql`: Database schema and initial data.

- **tests/**: Documentation for testing the application.
  - `README.md`: Testing guidelines.

- `composer.json`: Dependency management and autoloading settings.

## Setup Instructions
1. Clone the repository to your local machine.
2. Navigate to the project directory.
3. Set up the database using the `migrate.sql` script.
4. Update the `config/config.php` file with your database connection details.
5. Run the application by accessing `public/index.php` in your web browser.

## Usage Guidelines
- Users can register and log in to access the chat functionality.
- The blog page allows users to read and interact with blog posts.
- Private chat functionality enables users to communicate directly with each other.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License.