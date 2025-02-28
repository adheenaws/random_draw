<?php
/*
Plugin Name: Random Draw Plugin
Description: A plugin to interact with the Random Draws API.
Version: 1.0
Author: Your Name
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
function rdp_add_admin_page() {
    add_menu_page(
        'Random Draw Plugin',
        'Random Draw',
        'manage_options',
        'random-draw-plugin',
        'rdp_admin_page_html',
        'dashicons-admin-generic',
        100
    );
}
add_action('admin_menu', 'rdp_add_admin_page');

// Admin page HTML
function rdp_admin_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['generate_token'])) {
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        $draw_name = sanitize_text_field($_POST['draw_name']);
        $draw_organisation = sanitize_text_field($_POST['draw_organisation']);
        $schedule_type = sanitize_text_field($_POST['schedule_type']);
        $schedule_date = sanitize_text_field($_POST['schedule_date']);
        $timezone = sanitize_text_field($_POST['timezone']);
    
        // Save email, password, draw name, and organisation to the options table
        update_option('rdp_email', $email);
        update_option('rdp_password', $password);
        update_option('rdp_draw_name', $draw_name);
        update_option('rdp_draw_organisation', $draw_organisation);
    
        // Handle file upload
        if (!empty($_FILES['csv_file']['name'])) {
            $uploaded_file = $_FILES['csv_file'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploaded_file, $upload_overrides);
    
            if ($movefile && !isset($movefile['error'])) {
                $file_path = $movefile['file'];
                rdp_generate_token_and_upload_file($email, $password, $draw_name, $draw_organisation, $schedule_type, $schedule_date, $timezone, $file_path);
            } else {
                echo '<div class="notice notice-error"><p>File upload failed: ' . $movefile['error'] . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Please upload a CSV file.</p></div>';
        }
    }

    if (isset($_POST['fetch_results'])) {
        rdp_fetch_results();
    }

    if (isset($_POST['delete_entry'])) {
        if (isset($_POST['delete_id'])) {
            $delete_id = intval($_POST['delete_id']);
            rdp_delete_entry($delete_id);
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';
    $results = $wpdb->get_results("SELECT * FROM $table_name");

  
        
      
// Retrieve saved email, password, draw name, and organisation
$saved_email = get_option('rdp_email', '');
$saved_password = get_option('rdp_password', '');
$saved_draw_name = get_option('rdp_draw_name', '');
$saved_draw_organisation = get_option('rdp_draw_organisation', '');

?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <h2>Generate Token</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="email">Email</label></th>
                <td><input name="email" type="email" id="email" value="<?php echo esc_attr($saved_email); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="password">Password</label></th>
                <td><input name="password" type="password" id="password" value="<?php echo esc_attr($saved_password); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="draw_name">Draw Name</label></th>
                <td><input name="draw_name" type="text" id="draw_name" value="<?php echo esc_attr($saved_draw_name); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="draw_organisation">Draw Organisation</label></th>
                <td><input name="draw_organisation" type="text" id="draw_organisation" value="<?php echo esc_attr($saved_draw_organisation); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="schedule_type">Schedule Type</label></th>
                <td>
                    <select name="schedule_type" id="schedule_type" class="regular-text">
                        <option value="immediate">Immediate</option>
                        <option value="schedule">Schedule</option>
                    </select>
                </td>
            </tr>
            <tr id="schedule_date_row" style="display: none;">
                <th scope="row"><label for="schedule_date">Schedule Date</label></th>
                <td><input name="schedule_date" type="datetime-local" id="schedule_date" class="regular-text"></td>
            </tr>

            <script>
            document.getElementById("schedule_type").addEventListener("change", function() {
                var scheduleDateRow = document.getElementById("schedule_date_row");
                if (this.value === "schedule") {
                    scheduleDateRow.style.display = "table-row";
                } else {
                    scheduleDateRow.style.display = "none";
                }
            });
            </script>

            <tr>
                <th scope="row"><label for="timezone">Timezone</label></th>
                <td>
                    <select name="timezone" id="timezone" class="regular-text" required>
                        <option value="">Select Timezone</option>
                        <option value="Africa/Abidjan">Africa/Abidjan</option>
                        <option value="Africa/Accra">Africa/Accra</option>
                        <option value="Africa/Addis_Ababa">Africa/Addis Ababa</option>
                        <option value="Africa/Algiers">Africa/Algiers</option>
                        <option value="Africa/Asmara">Africa/Asmara</option>
                        <option value="Africa/Bamako">Africa/Bamako</option>
                        <option value="Africa/Cairo">Africa/Cairo</option>
                        <option value="America/New_York">America/New York</option>
                        <option value="America/Chicago">America/Chicago</option>
                        <option value="America/Los_Angeles">America/Los Angeles</option>
                        <option value="Asia/Tokyo">Asia/Tokyo</option>
                        <option value="Asia/Dubai">Asia/Dubai</option>
                        <option value="Asia/Kolkata">Asia/Kolkata</option>
                        <option value="Asia/Shanghai">Asia/Shanghai</option>
                        <option value="Australia/Sydney">Australia/Sydney</option>
                        <option value="Europe/London">Europe/London</option>
                        <option value="Europe/Paris">Europe/Paris</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="csv_file">CSV File</label></th>
                <td><input name="csv_file" type="file" id="csv_file" accept=".csv" required></td>
            </tr>
        </table>
        <?php submit_button('Generate Token', 'primary', 'generate_token'); ?>
    </form>

    <h2>Fetch Results</h2>
    <form method="post" action="">
        <?php submit_button('Fetch Results', 'primary', 'fetch_results'); ?>
    </form>

    <h2>Draw Results</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Draw ID</th>
                <th>Draw Name</th>
                <th>Draw Organisation</th>
                <th>Draw Date</th>
                <th>Winner No</th>
                <th>Entry No</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $result): ?>
            <tr>
                <td><?php echo esc_html($result->draw_id); ?></td>
                <td><?php echo esc_html($result->draw_name); ?></td>
                <td><?php echo esc_html($result->draw_organisation); ?></td>
                <td><?php echo esc_html($result->draw_date); ?></td>
                <td><?php echo esc_html($result->winner_no); ?></td>
                <td><?php echo esc_html($result->entry_no); ?></td>
                <td><?php echo esc_html($result->first_name); ?></td>
                <td><?php echo esc_html($result->last_name); ?></td>
                <td><?php echo esc_html($result->email); ?></td>
                <td>
                    <form method="post" action="">
                        <input type="hidden" name="delete_id" value="<?php echo esc_attr($result->id); ?>">
                        <?php submit_button('Delete', 'delete', 'delete_entry', false); ?>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
}

function rdp_delete_entry($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';
    $wpdb->delete($table_name, array('id' => $id));

    if ($wpdb->last_error) {
        error_log('Delete failed: ' . $wpdb->last_error);
        echo '<div class="notice notice-error"><p>Failed to delete entry. Check the logs for more details.</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Entry deleted successfully!</p></div>';
    }
}

// Function to manually create the database table
function rdp_create_database_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            draw_id varchar(255) NOT NULL,
            draw_name varchar(255) NOT NULL,
            draw_organisation varchar(255) NOT NULL,
            draw_date datetime NOT NULL,
            winner_no int NOT NULL,
            entry_no int NOT NULL,
            first_name varchar(255) NOT NULL,
            last_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_entry (draw_id, email) -- Add unique constraint
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Execute the query
        $wpdb->query($sql);

        // Log success or error
        if ($wpdb->last_error) {
            error_log('Table creation failed: ' . $wpdb->last_error);
        } else {
            error_log('Table created successfully: ' . $table_name);
        }
    } else {
        error_log('Table already exists: ' . $table_name);
    }
}
register_activation_hook(__FILE__, 'rdp_create_database_table');

// Function to generate token, upload file, create draw, confirm draw, and display winner details
function rdp_generate_token_and_upload_file($email, $password, $draw_name, $draw_organisation, $schedule_type, $schedule_date, $timezone, $file_path) {
    $base_url = 'https://api.randomdraws.com';
    
    // Step 1: Generate Token
    $token_url = $base_url . '/tokens';
    $token_data = array(
        'email' => $email,
        'password' => $password
    );

    $token_response = wp_remote_post($token_url, array(
        'body' => json_encode($token_data),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($token_response)) {
        error_log('Token request failed: ' . $token_response->get_error_message());
        echo '<div class="notice notice-error"><p>Token request failed. Check the logs for more details.</p></div>';
        return;
    }

    $token_body = wp_remote_retrieve_body($token_response);
    $token_data = json_decode($token_body, true);

    if (empty($token_data['token'])) {
        error_log('Token not found in response: ' . print_r($token_body, true));
        echo '<div class="notice notice-error"><p>Token not found in response. Check the logs for more details.</p></div>';
        return;
    }

    $token = $token_data['token'];
    set_transient('rdp_token', $token, 3600); // Token expires in 1 hour

    echo '<div class="notice notice-success"><p>Token generated successfully!</p></div>';

    // Step 2: Upload File
    $upload_url = $base_url . '/upload';

    if (!file_exists($file_path)) {
        error_log('File not found: ' . $file_path);
        echo '<div class="notice notice-error"><p>File not found. Check the logs for more details.</p></div>';
        return;
    }

    // Use cURL for file upload
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $upload_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'Content-Type: multipart/form-data'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'file' => new CURLFile($file_path)
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increase timeout to 30 seconds

    $upload_response = curl_exec($ch);
    $upload_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($upload_status !== 200) {
        error_log('File upload failed. Status: ' . $upload_status . ' Response: ' . $upload_response);
        echo '<div class="notice notice-error"><p>File upload failed. Check the logs for more details.</p></div>';
        return;
    }

    $upload_data = json_decode($upload_response, true);
    $upload_filename = $upload_data['filename'];
    set_transient('rdp_upload_filename', $upload_filename, 3600); // Filename expires in 1 hour

    echo '<div class="notice notice-success"><p>File uploaded successfully!</p></div>';

    // Step 3: Create Draw
    $draw_url = $base_url . '/draws';

    // Determine if the draw is scheduled based on the schedule_type
    $isScheduled = ($schedule_type === 'schedule');

    $draw_data = array(
        'name' => $draw_name,
        'organisation' => $draw_organisation,
        'uploadFilename' => $upload_filename,
        'headerRowsIncluded' => true,
        'prizes' => array(array(
            'id' => 1,
            'quantity' => 1,
            'reserves' => 0,
            'description' => 'API PRIZE'
        )),
        'isScheduled' => $isScheduled,
        'scheduleDate' => $isScheduled ? $schedule_date : null,
        'timezone' => $timezone
    );

    $draw_response = wp_remote_post($draw_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($draw_data),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($draw_response)) {
        error_log('Draw creation failed: ' . $draw_response->get_error_message());
        echo '<div class="notice notice-error"><p>Draw creation failed. Check the logs for more details.</p></div>';
        return;
    }

    $draw_body = wp_remote_retrieve_body($draw_response);
    $draw_data = json_decode($draw_body, true);

    if (empty($draw_data['drawId'])) {
        error_log('Draw ID not found in response: ' . print_r($draw_body, true));
        echo '<div class="notice notice-error"><p>Draw ID not found in response. Check the logs for more details.</p></div>';
        return;
    }

    $draw_id = $draw_data['drawId'];
    set_transient('rdp_draw_id', $draw_id, 3600);

    echo '<div class="notice notice-success"><p>Draw created successfully!</p></div>';

    // Step 4: Confirm Draw
    $confirm_url = $base_url . '/draws/' . $draw_id;

    $confirm_response = wp_remote_post($confirm_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($confirm_response)) {
        error_log('Draw confirmation failed: ' . $confirm_response->get_error_message());
        echo '<div class="notice notice-error"><p>Draw confirmation failed. Check the logs for more details.</p></div>';
        return;
    }

    echo '<div class="notice notice-success"><p>Draw confirmed successfully!</p></div>';

    // Step 5: Fetch Draw Results
    $results_url = $base_url . '/draws/' . $draw_id . '/winners.csv';

    // Add a delay to ensure the draw is processed (if necessary)
    sleep(5); // Wait for 5 seconds before fetching results

    $results_response = wp_remote_get($results_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($results_response)) {
        error_log('Fetching draw results failed: ' . $results_response->get_error_message());
        echo '<div class="notice notice-error"><p>Fetching draw results failed. Check the logs for more details.</p></div>';
        return;
    }

    $results_body = wp_remote_retrieve_body($results_response);

    if (empty($results_body)) {
        error_log('No results found in response: ' . print_r($results_body, true));
        echo '<div class="notice notice-error"><p>No results found in response. Check the logs for more details.</p></div>';
        return;
    }

    // Display Success Message
    echo '<div class="notice notice-success"><p>Results fetched successfully!</p></div>';
    // echo '<pre>';
    // print_r($results_body);
    // echo '</pre>';
}

// Function to fetch results and store in database
function rdp_fetch_results() {
    $base_url = 'https://api.randomdraws.com';
    $token = get_transient('rdp_token');
    $draw_id = get_transient('rdp_draw_id');

    if (!$token || !$draw_id) {
        echo '<div class="notice notice-error"><p>Token or Draw ID not found. Please generate a token and create a draw first.</p></div>';
        return;
    }

    // Fetch Draw Details to get Draw Name and Organisation
    $draw_details_url = $base_url . '/draws/' . $draw_id;
    $draw_details_response = wp_remote_get($draw_details_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($draw_details_response)) {
        error_log('Fetching draw details failed: ' . $draw_details_response->get_error_message());
        echo '<div class="notice notice-error"><p>Fetching draw details failed. Check the logs for more details.</p></div>';
        return;
    }

    $draw_details_body = wp_remote_retrieve_body($draw_details_response);
    $draw_details = json_decode($draw_details_body, true);

    if (empty($draw_details)) {
        error_log('No draw details found in response: ' . print_r($draw_details_body, true));
        echo '<div class="notice notice-error"><p>No draw details found in response. Check the logs for more details.</p></div>';
        return;
    }

    $draw_name = $draw_details['name'];
    $draw_organisation = $draw_details['organisation'];

    // Fetch Draw Results
    $results_url = $base_url . '/draws/' . $draw_id . '/api-winners.csv';
    $results_response = wp_remote_get($results_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($results_response)) {
        error_log('Fetching draw results failed: ' . $results_response->get_error_message());
        echo '<div class="notice notice-error"><p>Fetching draw results failed. Check the logs for more details.</p></div>';
        return;
    }

    $results_body = wp_remote_retrieve_body($results_response);

    if (empty($results_body)) {
        error_log('No results found in response: ' . print_r($results_body, true));
        echo '<div class="notice notice-error"><p>No results found in response. Check the logs for more details.</p></div>';
        return;
    }

    // Parse the CSV response
    $lines = explode("\n", $results_body);
    $headers = str_getcsv(array_shift($lines));

    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';

    foreach ($lines as $line) {
        $data = str_getcsv($line);
        if (count($data) === count($headers)) {
            $email = $data[5]; // Assuming email is in the 6th column

            // Check if the entry already exists
            $existing_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE draw_id = %s AND email = %s",
                $draw_id,
                $email
            ));

            if (!$existing_entry) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'draw_id' => $draw_id,
                        'draw_name' => $draw_name,
                        'draw_organisation' => $draw_organisation,
                        'draw_date' => current_time('mysql'),
                        'winner_no' => $data[1],
                        'entry_no' => $data[2],
                        'first_name' => $data[3],
                        'last_name' => $data[4],
                        'email' => $email
                    )
                );
            }
        }
    }

    // Display Success Message
    echo '<div class="notice notice-success"><p>Results fetched and stored successfully!</p></div>';
}