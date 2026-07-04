# SYSTEM BLUEPRINT

## 1. System Familiarization Summary

This document provides a technical overview of the CARELINK system.

### 1.1. Core Technologies

*   **Frontend:**
    *   **HTML5:** For structuring the content and layout of the application's pages.
    *   **CSS3:** For styling the user interface.
    *   **JavaScript (ES6):** For client-side interactivity and dynamic content.
*   **Backend (Planned):**
    *   **PHP:** Will be used for all server-side logic, including database interaction, user authentication, and API endpoints.
*   **External Libraries:**
    *   **Chart.js:** Integrated for data visualization, specifically for rendering charts on the dashboard pages.
    *   **Font Awesome:** Utilized for iconography throughout the application.

### 1.2. System Architecture

*   **Current Architecture:** The project follows a traditional multi-page web application architecture. It does not use a modern single-page application (SPA) framework.
*   **Future Architecture:** The system will evolve into a client-server model. The frontend HTML/CSS/JS will remain as is, but will be enhanced to make API calls to a PHP backend.
*   **Structure:** Each major feature or view is encapsulated in its own `.php` file.
*   **Application Forms:** The system supports distinct application processes for Senior Citizens and Persons With Disabilities (PWD), each potentially requiring different forms or specific data fields to be filled.
*   **Styling:** The application uses a combination of inline CSS and shared stylesheets. A consistent design language is established through CSS variables.
*   **Real-time Updates:** The system uses a polling mechanism to fetch real-time data from the server. The `assets/js/realtime_updates.js` file contains the logic for fetching and updating the dashboard statistics and notifications every 5 seconds.

### 1.3. Data Management and Flow

*   **Data Segregation by Barangay:** Data access within the system is strictly segregated by barangay. Barangay staff users can only view and manage application data pertinent to their assigned barangay, ensuring data relevance and operational focus.

*   **Database:** A MySQL/MariaDB database is used to persist data. PHP is used to connect to and manage the database.
*   **Data Flow:**
    1.  The client-side JavaScript will use the `fetch` API to make asynchronous requests to the PHP backend.
    2.  The PHP backend will process these requests, interact with the database, and return data in JSON format.
    3.  **Real-time Updates:** JavaScript will implement a polling mechanism using `setInterval` to periodically fetch updated data from dedicated PHP API endpoints. This will allow for real-time (or near real-time) updates of dashboard statistics, notifications, and other dynamic content across all relevant pages.

### 1.4. Development Environment

*   **Local Hosting:** The application is developed and hosted locally using **XAMPP**.
*   **Components:** XAMPP provides the necessary components for development:
    *   **Apache:** As the web server.
    *   **MariaDB:** As the database server (compatible with MySQL).
    *   **PHP:** As the backend scripting language.

## 2. Database Schema

### `users` table

| Column | Type | Modifiers | Description |
| --- | --- | --- | --- |
| id | INT(11) | NOT NULL, AUTO_INCREMENT, PRIMARY KEY | Unique identifier for each user |
| username | VARCHAR(50) | NOT NULL, UNIQUE | User's login name |
| password | VARCHAR(255) | NOT NULL | Hashed password for the user |
| role | ENUM('barangay_staff', 'department_admin') | NOT NULL | Role of the user in the system |
| first_name | VARCHAR(100) | NOT NULL | User's first name |
| last_name | VARCHAR(100) | NOT NULL | User's last name |
| email | VARCHAR(100) | NOT NULL, UNIQUE | User's email address |
| barangay | VARCHAR(100) | DEFAULT NULL | Barangay the user belongs to |
| display_name | VARCHAR(100) | DEFAULT NULL | User's display name |
| phone | VARCHAR(20) | DEFAULT NULL | User's phone number |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Timestamp of when the user was created |

### `settings` table

| Column | Type | Modifiers | Description |
| --- | --- | --- | --- |
| id | INT(11) | NOT NULL, AUTO_INCREMENT, PRIMARY KEY | Unique identifier for each setting |
| user_id | INT(11) | NOT NULL, FOREIGN KEY | Foreign key to the `users` table |
| theme | VARCHAR(50) | NOT NULL, DEFAULT 'light' | User's preferred theme (light, dark, auto) |
| language | VARCHAR(50) | NOT NULL, DEFAULT 'en' | User's preferred language (en, fil) |
| notifications | VARCHAR(50) | NOT NULL, DEFAULT 'all' | User's notification preferences (all, important, none) |

### `notifications` table

| Column | Type | Modifiers | Description |
| --- | --- | --- | --- |
| id | INT(11) | NOT NULL, AUTO_INCREMENT, PRIMARY KEY | Unique identifier for each notification |
| message | TEXT | NOT NULL | Notification message |
| type | VARCHAR(50) | NOT NULL | Type of notification (e.g., new_application, pending_verification) |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Timestamp of when the notification was created |

### `remember_tokens` table

| Column | Type | Modifiers | Description |
| --- | --- | --- | --- |
| id | INT(11) | NOT NULL, AUTO_INCREMENT, PRIMARY KEY | Unique identifier for each token |
| user_id | INT(11) | NOT NULL, FOREIGN KEY | Foreign key to the `users` table |
| selector | VARCHAR(12) | NOT NULL, UNIQUE | Selector for the remember me token |
| validator_hash | VARCHAR(64) | NOT NULL | Hashed validator for the remember me token |
| expires | DATETIME | NOT NULL | Expiry date for the token |

### `applications` table

| Column                 | Type                                  | Null | Key | Default             | Extra | Description                                       |
| :--------------------- | :------------------------------------ | :--- | :-- | :------------------ | :---- | :------------------------------------------------ |
| id_number              | varchar(255)                          | NO   | PRI | NULL                |       | Unique application ID, serves as Primary Key      |
| full_name              | varchar(255)                          | NO   |     | NULL                |       | Applicant's full name (generated)                 |
| application_type       | enum('pwd','senior')                  | NO   |     | NULL                |       | Type of application (e.g., PWD, Senior)           |
| birth_date             | date                                  | NO   |     | NULL                |       | Applicant's birth date                            |
| contact_number         | varchar(20)                           | NO   |     | NULL                |       | Applicant's contact number                        |
| complete_address       | text                                  | NO   |     | NULL                |       | Applicant's complete address                      |
| emergency_contact      | varchar(20)                           | NO   |     | NULL                |       | Emergency contact number                          |
| emergency_contact_name | varchar(255)                          | YES  |     | NULL                |       | Emergency contact name                            |
| date_submitted         | timestamp                             | NO   |     | current_timestamp() |       | Timestamp of when the application was submitted   |
| status                 | enum('pending','approved','rejected') | NO   |     | pending             |       | Status of the application                         |
| barangay               | varchar(100)                          | NO   |     | NULL                |       | Barangay of the applicant                         |
| proof_of_address       | mediumblob                            | YES  |     | NULL                |       | Proof of address document (BLOB)                  |
| proof_of_address_type  | varchar(100)                          | YES  |     | NULL                |       | Mime type of the proof of address                 |
| id_image               | mediumblob                            | YES  |     | NULL                |       | ID image (BLOB)                                   |
| id_image_type          | varchar(100)                          | YES  |     | NULL                |       | Mime type of the ID image                         |
| lastName               | varchar(255)                          | YES  |     | NULL                |       | Applicant's last name                             |
| firstName              | varchar(255)                          | YES  |     | NULL                |       | Applicant's first name                            |
| middleName             | varchar(255)                          | YES  |     | NULL                |       | Applicant's middle name                           |
| suffix                 | varchar(255)                          | YES  |     | NULL                |       | Applicant's suffix                                |
| disability_type        | varchar(255)                          | YES  |     | NULL                |       | Type of disability (for PWD applications)         |

## 3. API Endpoints

### `api/admin_approved_application.php`

*   **Functionality:** Approves an application.
*   **Method:** POST
*   **Parameters:** `id` (application ID)

### `api/admin_rejected_application.php`

*   **Functionality:** Rejects an application.
*   **Method:** POST
*   **Parameters:** `id` (application ID)

### `api/approve_application.php`

*   **Functionality:** Approves an application.
*   **Method:** POST
*   **Parameters:** `id` (application ID)

### `api/delete_application.php`

*   **Functionality:** Deletes an application.
*   **Method:** POST
*   **Parameters:** `id` (application ID)

### `api/get_all_applications.php`

*   **Functionality:** Retrieves all applications.
*   **Method:** GET

### `api/get_application_details.php`

*   **Functionality:** Retrieves the details of a specific application.
*   **Method:** GET
*   **Parameters:** `id` (application ID)

### `api/get_document.php`

*   **Functionality:** Retrieves a specific document for an application.
*   **Method:** GET
*   **Parameters:** `id` (application ID), `doc_type` (document type)

### `api/get_realtime_data.php`

*   **Functionality:** Retrieves real-time data for the dashboard.
*   **Method:** GET

### `api/import_applications.php`

*   **Functionality:** Imports applications from a CSV file.
*   **Method:** POST

### `api/search_applications.php`

*   **Functionality:** Searches for applications based on a query.
*   **Method:** GET
*   **Parameters:** `query`, `type`, `status`

## 4. File-Specific Instructions

### `index.php`

*   **Functionality:** This is the main entry point of the application. It allows users to select their role (Barangay Staff or Department Admin) and be redirected to the appropriate login page.
*   **Remember Me:** If the user has previously logged in and selected "Remember Me", this page will automatically log them in and redirect them to their dashboard.

### `pages/signup.php`

*   **Functionality:** This page allows new users to register for an account.
*   **Validation:** The page performs validation to ensure that all required fields are filled, the passwords match, the password is at least 8 characters long, and the email format is valid.
*   **User Creation:** Upon successful validation, a new user is created in the `users` table, and a corresponding default entry is created in the `settings` table.

### `pages/Barangay_Staff_LogInPage.php` and `pages/Department_Admin_LogIn_Page.php`

*   **Functionality:** These pages allow users to log in to their accounts.
*   **Validation:** The pages perform validation to ensure that all required fields are filled and that the user exists in the database.
*   **Authentication:** Upon successful validation, the user is authenticated, and a session is created.
*   **Remember Me:** Users can choose to be remembered, which sets a cookie to keep them logged in for 30 days.

### `pages/Settings.php`

*   **Functionality:** This page allows users to manage their profile, system, and security settings.
*   **Profile Settings:** Users can edit their display name, email, and phone number.
*   **System Settings:** Users can customize the theme, language, and notification preferences.
*   **Security Settings:** Users can change their password by providing their current password and a new password.

### `pages/edit_user.php`

*   **Functionality:** This page allows administrators to edit existing user information.
*   **Features:**
    *   Edit user details such as username and email.
    *   Display the user's current hashed password (for administrative reference, though direct display of plain text passwords is a security risk).
    *   Option to change the user's password.
*   **Security Note:** Displaying the raw password is a significant security vulnerability and is implemented here based on explicit user request. In a production environment, only password change functionality should be provided, without displaying the current password.

### `pages/new_application.php`

*   **Functionality:** This page allows barangay staff to submit a new application.
*   **Features:**
    *   A form to enter all the applicant's information.
    *   File uploads for required documents.

### `pages/submit_application.php`

*   **Functionality:** This page allows barangay staff to view and manage applications.
*   **Features:**
    *   A table of all applications with search and filter functionality.
    *   A modal to view the details of a specific application.
    *   A modal to import applications from a CSV file.

### `pages/barangay_records.php`

*   **Functionality:** This page allows barangay staff to view and manage records.
*   **Features:**
    *   A table of all records with search and filter functionality.
    *   A yearly record view.

### `pages/department_records.php`

*   **Functionality:** This page allows department admins to view and manage records.
*   **Features:**
    *   A table of all records with search and filter functionality.

### `pages/verify_document.php`

*   **Functionality:** This page allows department admins to verify documents.
*   **Features:**
    *   A table of all applications with a button to view the details and verify the documents.

## 5. CSS and JS

### `assets/css/barangay-sidebar.css`

*   **Functionality:** This file contains the styles for the sidebar used in the barangay pages.

### `assets/css/department-sidebar.css`

*   **Functionality:** This file contains the styles for the sidebar used in the department pages.

### `assets/css/loading-spinner.css`

*   **Functionality:** This file contains the styles for the loading spinner.

### `assets/js/dynamic-loader.js`

*   **Functionality:** This file contains the logic for showing and hiding the loading spinner.

### `assets/js/realtime_updates.js`

*   **Functionality:** This file contains the logic for fetching and updating the dashboard statistics and notifications in real-time.

The document verification workflow is managed directly through the PHP application and manual review process. It uses a Finite State Machine (FSM) to transition application states and a Localized Compliance Engine to validate age and ordinance constraints.

## 7. Deployment Guide (Render)

This guide provides the steps to deploy the PHP/MySQL application to the Render platform.

### Step 1: Get Your Project on GitHub
Render deploys from GitHub. You must have all your code in a repository.

1.  **Create GitHub Account:** If you don't have one, create a free account at [github.com](https://github.com).
2.  **Create New Repository:** Create a new, **public**, empty repository. Do not add a `README` or `.gitignore` from the web interface.
3.  **Upload Your Project:** Use `git` commands to upload your entire project folder to the new repository.

### Step 2: Set Up a Free MySQL Database
Render's free databases are temporary and are PostgreSQL. To keep using MySQL for free, you must use an external service.

1.  **Choose a Provider:** Go to a site like [freemysqlhosting.net](https://www.freemysqlhosting.net/) or [db4free.net](https://www.db4free.net/).
2.  **Create Database:** Sign up and create a new database.
3.  **Note Credentials:** Carefully copy the **database name**, **username**, **password**, and **server hostname**.
4.  **Import Data:** Use their provided phpMyAdmin to import the `.sql` backup file of your `carelink_db` database.

### Step 3: Prepare the PHP App with Docker
To run PHP on Render, you must provide a `Dockerfile`.

1.  **Create the File:** In the **root** of your project folder, create a new file named `Dockerfile` (no extension).
2.  **Add Docker Instructions:** Copy and paste the following code into the file:
    ```dockerfile
    # Use an official PHP image with an Apache web server
    FROM php:8.2-apache

    # Install the MySQLi extension that your PHP code needs
    RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

    # Copy all your project files into the web server's root directory
    COPY . /var/www/html/
    ```

### Step 4: Deploy the PHP App & Configure Environment
1.  **New Web Service:** In Render, create a **New > Web Service**, using your GitHub repository.
2.  **Configure the PHP Service:**
    *   **Name:** Give it a different name (e.g., `carelink-web`).
    *   **Root Directory:** Leave this blank.
    *   **Runtime:** Set this to **`Docker`**.
    *   **Instance Type:** `Free`.
3.  **Add Environment Variables:** Before creating the service, click on **Advanced**. Create the following key-value pairs:
    *   `DB_HOST`: The server hostname from your MySQL provider.
    *   `DB_USER`: The username for your database.
    *   `DB_PASS`: The password for your database.
    *   `DB_NAME`: The name of your database.

### Step 5: Update Your PHP Code
Edit `includes/db_connect.php` to use the Environment Variables instead of hard-coded values.
```php
<?php
// Get credentials from environment variables set in Render
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

// Establish connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```
Commit and push this final change to GitHub. Render will automatically see the change and redeploy your PHP service. Your site should now be live at your `carelink-web` URL.
