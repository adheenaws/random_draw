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
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>WooCommerce is not active. Please activate WooCommerce to use this plugin.</p></div>';
        return;
    }

    if (isset($_POST['delete_all_results'])) {
        if (current_user_can('manage_options')) {
            rdp_delete_all_results();
        } else {
            echo '<div class="notice notice-error"><p>You do not have sufficient permissions to perform this action.</p></div>';
        }
    }

    if (isset($_POST['generate_token'])) {
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        $draw_name = sanitize_text_field($_POST['draw_name']);
        $draw_organisation = sanitize_text_field($_POST['draw_organisation']);
        $schedule_type = sanitize_text_field($_POST['schedule_type']);
        $schedule_date = sanitize_text_field($_POST['schedule_date']);
        $timezone = sanitize_text_field($_POST['timezone']);
        $selected_product_id = intval($_POST['selected_product']); // Get selected product ID

        // Save email, password, draw name, and organisation to the options table
        update_option('rdp_email', $email);
        update_option('rdp_password', $password);
        update_option('rdp_draw_name', $draw_name);
        update_option('rdp_draw_organisation', $draw_organisation);
        update_option('rdp_selected_product', $selected_product_id); // Save selected product ID
    
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
    if (isset($_POST['fetch_draw_details'])) {
        rdp_fetch_and_store_draw_details();
    }

    if (isset($_POST['delete_entry'])) {
        if (isset($_POST['delete_id'])) {
            $delete_id = intval($_POST['delete_id']);
            rdp_delete_entry($delete_id);
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY draw_date DESC LIMIT 10");

    // Fetch draw details from the database
    $draw_details_table_name = $wpdb->prefix . 'random_draw_details';
    $draw_details = $wpdb->get_results("SELECT * FROM $draw_details_table_name");


            // Retrieve saved email, password, draw name, and organisation
    $saved_email = get_option('rdp_email', '');
    $saved_password = get_option('rdp_password', '');
    $saved_draw_name = get_option('rdp_draw_name', '');
    $saved_draw_organisation = get_option('rdp_draw_organisation', '');
    $saved_selected_product = get_option('rdp_selected_product', ''); // Retrieve saved selected product

// Fetch WooCommerce products
$products = wc_get_products(array('status' => 'publish', 'limit' => -1));
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
                    <th scope="row"><label for="selected_product">Competition</label></th>
                    <td>
                        <select name="selected_product" id="selected_product" class="regular-text" required>
                            <option value="">Select a Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($saved_selected_product, $product->get_id()); ?>>
                                    <?php echo esc_html($product->get_name()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
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

    <h2>Fetch Draw Details</h2>
<form method="post" action="">
    <?php submit_button('Fetch Draw Details', 'primary', 'fetch_draw_details'); ?>
</form>
<h2>Active Scheduled Draws</h2>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Draw ID</th>
            <th>Draw Name</th>
            <th>Organisation</th>
            <th>Schedule Date</th>
            <th>Timezone</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($draw_details)): ?>
            <?php foreach ($draw_details as $draw): ?>
                <?php if ($draw->is_scheduled && strtotime($draw->schedule_date) > time()): ?>
                    <tr>
                        <td><?php echo esc_html($draw->draw_id); ?></td>
                        <td><?php echo esc_html($draw->draw_name); ?></td>
                        <td><?php echo esc_html($draw->draw_organisation); ?></td>
                        <td><?php echo esc_html($draw->schedule_date); ?></td>
                        <td><?php echo esc_html($draw->timezone); ?></td>
                        <td>
                            <button class="edit-draw-button" data-draw-id="<?php echo esc_attr($draw->draw_id); ?>" data-draw-name="<?php echo esc_attr($draw->draw_name); ?>" data-draw-organisation="<?php echo esc_attr($draw->draw_organisation); ?>" data-schedule-date="<?php echo esc_attr($draw->schedule_date); ?>" data-timezone="<?php echo esc_attr($draw->timezone); ?>">Edit</button>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No active scheduled draws found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
<!-- Pop-up Modal -->
<!-- Pop-up Modal -->
<div id="editDrawModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Draw Details</h2>
        <form id="editDrawForm">
            <input type="hidden" id="editDrawId">
            <label for="editDrawName">Draw Name:</label>
            <input type="text" id="editDrawName" name="editDrawName">
            <label for="editDrawOrganisation">Organisation:</label>
            <input type="text" id="editDrawOrganisation" name="editDrawOrganisation">
            <label for="editScheduleDate">Schedule Date:</label>
            <input type="datetime-local" id="editScheduleDate" name="editScheduleDate">
            <label for="editTimezone">Timezone:</label>
            <select id="editTimezone" name="editTimezone">
                <option value="Africa/Abidjan">Africa/Abidjan</option>
                <option value="Africa/Accra">Africa/Accra</option>
                <!-- Add other timezone options here -->
            </select>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>


<style>
    /* Modal CSS */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgb(0,0,0);
        background-color: rgba(0,0,0,0.4);
        padding-top: 60px;
    }
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
    }
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }
    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    function fetchResults() {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rdp_fetch_results'
            },
            success: function(response) {
                jQuery('#results-table').html(response);
            }
        });
    }

    // Fetch results every 60 seconds
    setInterval(fetchResults, 60000);

    // Initial fetch
    fetchResults();
});
   document.addEventListener('DOMContentLoaded', function() {
    // Get the modal
    var modal = document.getElementById("editDrawModal");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks on the button, open the modal
    document.querySelectorAll('.edit-draw-button').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('editDrawId').value = this.getAttribute('data-draw-id');
            document.getElementById('editDrawName').value = this.getAttribute('data-draw-name');
            document.getElementById('editDrawOrganisation').value = this.getAttribute('data-draw-organisation');
            document.getElementById('editScheduleDate').value = this.getAttribute('data-schedule-date');
            document.getElementById('editTimezone').value = this.getAttribute('data-timezone');
            modal.style.display = "block";
        });
    });

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    document.getElementById('editDrawForm').addEventListener('submit', function(event) {
    event.preventDefault();

    // Get form data
    var drawId = document.getElementById('editDrawId').value;
    var drawName = document.getElementById('editDrawName').value;
    var drawOrganisation = document.getElementById('editDrawOrganisation').value;
    var scheduleDate = document.getElementById('editScheduleDate').value;
    var timezone = document.getElementById('editTimezone').value;

    // Prepare data for AJAX request
    var data = {
        'action': 'update_draw_details',
        'draw_id': drawId,
        'draw_name': drawName,
        'draw_organisation': drawOrganisation,
        'schedule_date': scheduleDate,
        'timezone': timezone
    };

    // Send AJAX request
    jQuery.post(ajaxurl, data, function(response) {
        console.log('AJAX Response:', response); // Log the response
        if (response.success) {
            alert('Draw details updated successfully!');
            location.reload(); // Reload the page to reflect changes
        } else {
            alert('Failed to update draw details: ' + response.data);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX Request Failed:', textStatus, errorThrown); // Log the error
        alert('AJAX Request Failed: ' + textStatus);
    });
});
});

</script>

<h2>Draw Details</h2>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Draw ID</th>
            <th>Draw Name</th>
            <th>Organisation</th>
            <th>Is Scheduled</th>
            <th>Schedule Date</th>
            <th>Timezone</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($draw_details)): ?>
            <?php foreach ($draw_details as $draw): ?>
                <?php if (!$draw->is_scheduled || strtotime($draw->schedule_date) <= time()): ?>
                    <tr>
                        <td><?php echo esc_html($draw->draw_id); ?></td>
                        <td><?php echo esc_html($draw->draw_name); ?></td>
                        <td><?php echo esc_html($draw->draw_organisation); ?></td>
                        <td><?php echo $draw->is_scheduled ? 'Yes' : 'No'; ?></td>
                        <td><?php echo esc_html($draw->schedule_date); ?></td>
                        <td><?php echo esc_html($draw->timezone); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No completed draw details found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>


    <h2>Fetch Results</h2>
    <form method="post" action="">
        <?php submit_button('Fetch Results', 'primary', 'fetch_results'); ?>
    </form>

    <h2>Draw Results</h2>
<div id="results-table">
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
    <h2>Delete All Results</h2>
<form method="post" action="">
    <?php submit_button('Delete All Results', 'delete', 'delete_all_results', false); ?>
</form>
</div>
<?php
}


function rdp_delete_all_results() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';
    $wpdb->query("TRUNCATE TABLE $table_name");

    if ($wpdb->last_error) {
        error_log('Delete all failed: ' . $wpdb->last_error);
        echo '<div class="notice notice-error"><p>Failed to delete all entries. Check the logs for more details.</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>All entries deleted successfully!</p></div>';
    }
}


function rdp_enqueue_admin_scripts($hook) {
    if ($hook != 'toplevel_page_random-draw-plugin') {
        return;
    }
    wp_enqueue_script('rdp-admin-script', plugins_url('admin-script.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('rdp-admin-script', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('admin_enqueue_scripts', 'rdp_enqueue_admin_scripts');


// Add AJAX handler for updating draw details
function rdp_update_draw_details() {
    // Validate input data
    if (!isset($_POST['draw_id']) || !isset($_POST['draw_name']) || !isset($_POST['draw_organisation']) || !isset($_POST['schedule_date']) || !isset($_POST['timezone'])) {
        wp_send_json_error('Invalid data provided.');
    }

    // Sanitize input data
    $draw_id = sanitize_text_field($_POST['draw_id']);
    $draw_name = sanitize_text_field($_POST['draw_name']);
    $draw_organisation = sanitize_text_field($_POST['draw_organisation']);
    $schedule_date = sanitize_text_field($_POST['schedule_date']);
    $timezone = sanitize_text_field($_POST['timezone']);

    // Get the API token
    $token = get_transient('rdp_token');
    if (!$token) {
        wp_send_json_error('Token not found or expired.');
    }

    // Prepare the request body
    $draw_data = array(
        'name' => $draw_name,
        'organisation' => $draw_organisation,
        'isScheduled' => true, // Assuming the draw is scheduled
        'scheduleDate' => $schedule_date,
        'timezone' => $timezone
    );

    // API endpoint
    $base_url = 'https://api.randomdraws.com';
    $draw_url = $base_url . '/draws/' . $draw_id;

    // Send the PUT request
    $response = wp_remote_request($draw_url, array(
        'method' => 'PUT',
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($draw_data),
        'timeout' => 30
    ));

    // Handle errors
    if (is_wp_error($response)) {
        error_log('API Request Failed: ' . $response->get_error_message());
        wp_send_json_error('API Request Failed: ' . $response->get_error_message());
    }

    // Check the response code
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    error_log('API Response Code: ' . $response_code);
    error_log('API Response Body: ' . print_r($response_body, true));

    if ($response_code !== 200) {
        error_log('API Error: ' . $response_body);
        wp_send_json_error('API Error: ' . $response_body);
    }

    if (isset($response_data['error'])) {
        error_log('API Error: ' . $response_data['error']);
        wp_send_json_error($response_data['error']);
    }

    // Success
    wp_send_json_success('Draw details updated successfully.');
}
add_action('wp_ajax_update_draw_details', 'rdp_update_draw_details');


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
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

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

function rdp_create_draw_details_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_details';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            draw_id varchar(255) NOT NULL,
            draw_name varchar(255) NOT NULL,
            draw_organisation varchar(255) NOT NULL,
            is_scheduled tinyint(1) NOT NULL,
            schedule_date datetime NOT NULL,
            timezone varchar(100) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_draw (draw_id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Execute the query
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

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
register_activation_hook(__FILE__, 'rdp_create_draw_details_table');


function rdp_fetch_and_store_draw_details() {
    $base_url = 'https://api.randomdraws.com';

    // Retrieve saved email and password
    $email = get_option('rdp_email', '');
    $password = get_option('rdp_password', '');

    if (empty($email) || empty($password)) {
        echo '<div class="notice notice-error"><p>Email and password are required to generate a token.</p></div>';
        return;
    }

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

    // Rest of the function remains the same...
    // Fetch Draw Details
    $draws_url = $base_url . '/draws/?page=1&count=5&sortField=DrawNumber&descending=true&showCancelled=false';
    $draws_response = wp_remote_get($draws_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($draws_response)) {
        error_log('Fetching draw details failed: ' . $draws_response->get_error_message());
        echo '<div class="notice notice-error"><p>Fetching draw details failed. Check the logs for more details.</p></div>';
        return;
    }

    $draws_body = wp_remote_retrieve_body($draws_response);
    $draws_data = json_decode($draws_body, true);

    // Log API response for debugging
    error_log('API Response: ' . print_r($draws_data, true));

    if (!is_array($draws_data) || !isset($draws_data['draws'])) {
        error_log('Invalid API response format: ' . print_r($draws_data, true));
        echo '<div class="notice notice-error"><p>Invalid API response format. Check the logs for more details.</p></div>';
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_details';

    // Clear existing draw details
    $wpdb->query("TRUNCATE TABLE $table_name");

    foreach ($draws_data['draws'] as $draw) {
        if (!is_array($draw)) {
            error_log('Invalid draw data format: ' . print_r($draw, true));
            continue;
        }

        // Extract data from the API response
        $draw_id = isset($draw['id']) ? $draw['id'] : '';
        $draw_name = isset($draw['name']) ? $draw['name'] : '';
        $draw_organisation = isset($draw['organisation']) ? $draw['organisation'] : '';
        $is_scheduled = isset($draw['isScheduled']) ? $draw['isScheduled'] : 0;
        $schedule_date = isset($draw['scheduleDate']) ? $draw['scheduleDate'] : '';
        $timezone = isset($draw['timezone']) ? $draw['timezone'] : '';

        // Check if the draw is active and scheduled
        if ($is_scheduled && strtotime($schedule_date) > time()) {
            // Insert or update the draw details
            $wpdb->replace(
                $table_name,
                array(
                    'draw_id' => $draw_id,
                    'draw_name' => $draw_name,
                    'draw_organisation' => $draw_organisation,
                    'is_scheduled' => $is_scheduled,
                    'schedule_date' => $schedule_date,
                    'timezone' => $timezone
                )
            );
        }
    }

    // Display Success Message
    echo '<div class="notice notice-success"><p>Active scheduled draw details fetched and stored successfully!</p></div>';
}

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

   // Get selected product ID and its name
   $selected_product_id = get_option('rdp_selected_product', 1);
   $selected_product = wc_get_product($selected_product_id);
   $prize_description = $selected_product ? $selected_product->get_name() : 'API PRIZE'; // Use product name as prize description

   $draw_data = array(
       'name' => $draw_name,
       'organisation' => $draw_organisation,
       'uploadFilename' => $upload_filename,
       'headerRowsIncluded' => true,
       'prizes' => array(array(
           'id' => $selected_product_id,
           'quantity' => 1,
           'reserves' => 0,
           'description' => $prize_description // Use product name as prize description
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
    $results_url = $base_url . '/draws/' . $draw_id . '/api-winners.csv';

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

    // Retrieve saved email and password
    $email = get_option('rdp_email', '');
    $password = get_option('rdp_password', '');

    if (empty($email) || empty($password)) {
        echo '<div class="notice notice-error"><p>Email and password are required to generate a token.</p></div>';
        return;
    }

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

    // Rest of the function remains the same...
    // Fetch all draws
    $draws_url = $base_url . '/draws/?page=1&count=100&sortField=DrawNumber&descending=true&showCancelled=false';
    $draws_response = wp_remote_get($draws_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($draws_response)) {
        error_log('Fetching draws failed: ' . $draws_response->get_error_message());
        echo '<div class="notice notice-error"><p>Fetching draws failed. Check the logs for more details.</p></div>';
        return;
    }

    $draws_body = wp_remote_retrieve_body($draws_response);
    $draws_data = json_decode($draws_body, true);

    if (empty($draws_data['draws'])) {
        error_log('No draws found in response: ' . print_r($draws_body, true));
        echo '<div class="notice notice-error"><p>No draws found in response. Check the logs for more details.</p></div>';
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';

    foreach ($draws_data['draws'] as $draw) {
        $draw_id = $draw['id'];
        $draw_name = $draw['name'];
        $draw_organisation = $draw['organisation'];

        // Fetch Draw Results for each draw
        $results_url = $base_url . '/draws/' . $draw_id . '/api-winners.csv';
        $results_response = wp_remote_get($results_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30 // Increase timeout to 30 seconds
        ));

        if (is_wp_error($results_response)) {
            error_log('Fetching draw results failed for draw ' . $draw_id . ': ' . $results_response->get_error_message());
            continue;
        }

        $results_body = wp_remote_retrieve_body($results_response);

        if (empty($results_body)) {
            error_log('No results found for draw ' . $draw_id . ': ' . print_r($results_body, true));
            continue;
        }

        // Parse the CSV response
        $lines = explode("\n", $results_body);
        $headers = str_getcsv(array_shift($lines));

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
    }

    // Display Success Message
    echo '<div class="notice notice-success"><p>Results fetched and stored successfully!</p></div>';
}

// Schedule a cron job to check for completed draws
function rdp_schedule_cron_job() {
    if (!wp_next_scheduled('rdp_check_completed_draws')) {
        wp_schedule_event(time(), 'hourly', 'rdp_check_completed_draws');
    }
}
add_action('wp', 'rdp_schedule_cron_job');

// Hook the function to the cron job
add_action('rdp_check_completed_draws', 'rdp_check_completed_draws');

function rdp_check_completed_draws() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_details';
    $draws = $wpdb->get_results("SELECT * FROM $table_name WHERE is_scheduled = 1 AND schedule_date <= NOW()");

    foreach ($draws as $draw) {
        rdp_fetch_results_for_draw($draw->draw_id);
    }
}

function rdp_fetch_results_for_draw($draw_id) {
    $base_url = 'https://api.randomdraws.com';
    $token = get_transient('rdp_token');

    if (!$token) {
        error_log('Token not found. Please generate a token first.');
        return;
    }

    $results_url = $base_url . '/draws/' . $draw_id . '/api-winners.csv';
    $results_response = wp_remote_get($results_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30 // Increase timeout to 30 seconds
    ));

    if (is_wp_error($results_response)) {
        error_log('Fetching draw results failed for draw ' . $draw_id . ': ' . $results_response->get_error_message());
        return;
    }

    $results_body = wp_remote_retrieve_body($results_response);

    if (empty($results_body)) {
        error_log('No results found for draw ' . $draw_id . ': ' . print_r($results_body, true));
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';

    // Parse the CSV response
    $lines = explode("\n", $results_body);
    $headers = str_getcsv(array_shift($lines));

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
                        'draw_name' => $draw['name'],
                        'draw_organisation' => $draw['organisation'],
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
}

function rdp_ajax_fetch_results() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'random_draw_results';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY draw_date DESC LIMIT 10");

    ob_start();
    ?>
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
    <?php
    $output = ob_get_clean();
    echo $output;
    wp_die();
}
add_action('wp_ajax_rdp_fetch_results', 'rdp_ajax_fetch_results');
add_action('wp_ajax_nopriv_rdp_fetch_results', 'rdp_ajax_fetch_results');