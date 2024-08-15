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
        if ($_POST['action'] === 'edit' && isset($_POST['client_id'])) {
            update_client($_POST['client_id']);
        } elseif ($_POST['action'] === 'delete' && isset($_POST['client_id'])) {
            delete_client($_POST['client_id']);
        } elseif ($_POST['action'] === 'add') {
            add_client();
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
        <input type="hidden" name="action" value="add">
        <label for="name">Name:</label>
        <input type="text" name="name" required><br>
        <label for="email">Email:</label>
        <input type="email" name="email" required><br>
        <label for="phone">Phone:</label>
        <input type="text" name="phone"><br>
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
            <tr>
                <td><?php echo esc_html($client->post_title); ?></td>
                <td><?php echo esc_html(get_post_meta($client->ID, 'email', true)); ?></td>
                <td><?php echo esc_html(get_post_meta($client->ID, 'phone', true)); ?></td>
                <td>
                    <a href="#" onclick="editClient(<?php echo $client->ID; ?>)">Edit</a> |
                    <form method="post" action="" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="client_id" value="<?php echo $client->ID; ?>">
                        <input type="submit" value="Delete" onclick="return confirm('Are you sure?');">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Edit Client Modal -->
<div id="editClientModal" style="display:none;">
    <h2>Edit Client</h2>
    <form method="post" action="">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="client_id" id="edit_client_id">
        <label for="edit_name">Name:</label>
        <input type="text" name="name" id="edit_name" required><br>
        <label for="edit_email">Email:</label>
        <input type="email" name="email" id="edit_email" required><br>
        <label for="edit_phone">Phone:</label>
        <input type="text" name="phone" id="edit_phone"><br>
        <input type="submit" value="Update Client">
    </form>
</div>

<script>
function editClient(clientId) {
    // Fetch client data and populate the form (you'll need to implement this part)
    // For now, we'll just show the modal
    document.getElementById('edit_client_id').value = clientId;
    document.getElementById('editClientModal').style.display = 'block';
}
</script>

<?php
get_footer();

// Helper functions

function get_clients() {
    $args = array(
        'post_type' => 'customer',
        'posts_per_page' => -1,
    );
    return get_posts($args);
}

function add_client() {
    $client_data = array(
        'post_title' => sanitize_text_field($_POST['name']),
        'post_type' => 'customer',
        'post_status' => 'publish',
    );
    $client_id = wp_insert_post($client_data);
    if ($client_id) {
        update_post_meta($client_id, 'email', sanitize_email($_POST['email']));
        update_post_meta($client_id, 'phone', sanitize_text_field($_POST['phone']));
    }
}

function update_client($client_id) {
    $client_data = array(
        'ID' => $client_id,
        'post_title' => sanitize_text_field($_POST['name']),
    );
    wp_update_post($client_data);
    update_post_meta($client_id, 'email', sanitize_email($_POST['email']));
    update_post_meta($client_id, 'phone', sanitize_text_field($_POST['phone']));
}

function delete_client($client_id) {
    wp_delete_post($client_id, true);
}
?>