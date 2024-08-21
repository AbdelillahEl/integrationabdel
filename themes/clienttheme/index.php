<?php
/*
Template Name: Clients Page
*/

get_header();

// Check if the user is logged in and has appropriate permissions
if (!is_user_logged_in() || !current_user_can('edit_posts')) {
    wp_die('You do not have permission to access this page.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'edit_client' && isset($_POST['client_id'])) {
            handle_form_submit_update_client();
        } elseif ($_POST['action'] === 'delete_client' && isset($_POST['client_id'])) {
            handle_form_submit_delete_client();
        } elseif ($_POST['action'] === 'add_client') {
            handle_form_submit_new_client();
        }
    }
}

// Display clients
$clients = get_clients();
?>

<div class="wrap">
    <h1>Clients</h1>

    <!-- Add New Client Form -->
    <h2>Add New Client</h2>
    <form method="post" action="">
        <input type="hidden" name="submit_client_form" value="submit_client_form">
        <label for="name">Name:</label>
        <input type="text" name="client_first_name" required><br>
        <label for="email">Email:</label>
        <input type="email" name="client_email" required><br>
        <label for="phone">Phone:</label>
        <input type="text" name="client_phone"><br>
        <input type="submit" value="Add Client">
    </form>

    <!-- Display Clients -->
    <h2>Client List</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($clients as $client): ?>
    <tr><form action="" method="POST">
        <input type="hidden" name="edit_client_form" value="edit_client">
        <input type="hidden" name="client_id" value="<?php echo $client->ID; ?>">
        <td><input type="text" name="client_first_name" value="<?php echo esc_html(get_post_meta($client->ID, 'client_first_name', true)); ?>"></td>
        <td><input type="text" name="client_email" value="<?php echo esc_html(get_post_meta($client->ID, 'client_email', true)); ?>"></td>
        <td><input type="text" name="client_number" value="<?php echo esc_html(get_post_meta($client->ID, 'client_phone', true)); ?>"></td>
        <td>
        <input type="submit" value="Edit">
            
        </form>
            <form method="post" action="" style="display:inline;">
                <input type="hidden" name="delete_client_form" value="delete_client">
                <input type="hidden" name="client_id" value="<?php echo $client->ID; ?>">
                <input type="submit" value="Delete" onclick="return confirm('Are you sure?');">
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
    </table>
</div>



<?php
get_footer();

// Helper functions

function get_clients() {
    $args = array(
        'post_type' => 'client',
        'posts_per_page' => -1,
    );
    return get_posts($args);
}

// Implement an AJAX handler for fetching client data
function get_client_data() {
    $client_id = intval($_POST['client_id']);
    $client = get_post($client_id);

    if ($client) {
        wp_send_json_success(array(
            'ID' => $client->ID,
            'first_name' => get_post_meta($client->ID, 'client_first_name', true),
            'email' => get_post_meta($client->ID, 'client_email', true),
            'phone' => get_post_meta($client->ID, 'client_phone', true),
        ));
    } else {
        wp_send_json_error('Client not found');
    }
}

add_action('wp_ajax_get_client_data', 'get_client_data');
?>
