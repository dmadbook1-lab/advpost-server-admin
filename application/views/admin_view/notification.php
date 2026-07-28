<?php $this->load->view('landing/sidebar'); ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    
<div class="content-wrapper" style="padding: 20px;">
    <h1 style="text-align: center; margin-bottom: 30px;"><b>Notification</b></h1>

    <div class="container" style="max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);">

        <!-- Notification Form -->
        <form action="<?php echo base_url('welcome/store_notification'); ?>" method="POST" style="display: flex; flex-wrap: wrap; gap: 20px;">

            <!-- Select Option -->
            <div style="flex: 1 1 48%;">
                <label for="target_type">Select Option:</label>
                <select id="target_type" name="target_type" onchange="loadNames(this.value)" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="">-- Select --</option>
                    <option value="all_user">All Users</option>
                    <option value="user">Specific User</option>
                </select>
            </div>

            <!-- Dynamically Loaded Names -->
            <div id="name_container" style="flex: 1 1 48%; display: none;">
                <label for="name_select">Select Name:</label>
                <select id="name_select" name="name_select" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="">-- Select Name --</option>
                </select>
            </div>

            <!-- Notification Text -->
            <div style="flex: 1 1 100%;">
                <label for="notification">Notification:</label>
                <textarea id="notification" name="notification" rows="4" required placeholder="Enter notification message"
                    style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; resize: vertical;"></textarea>
            </div>

            <!-- Submit Button -->
            <div style="flex: 1 1 100%; text-align: center;">
                <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Send Notification
                </button>
            </div>

        </form>
    </div>
</div>

<?php $this->load->view('landing/footerone.php'); ?>

<script>
function loadNames(type) {
    const nameContainer = document.getElementById('name_container');
    const nameSelect = document.getElementById('name_select');

    if(type === 'user') {
        nameContainer.style.display = 'block';
        nameSelect.innerHTML = '<option value="">-- Select Name --</option>';

        fetch('<?php echo base_url("index.php/welcome/fetch_names"); ?>', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            console.log(data); // check data in console
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id; // use id for value
                option.textContent = item.first_name;
                nameSelect.appendChild(option);
            });
        })
        .catch(err => console.error(err));

    } else {
        nameContainer.style.display = 'none';
    }
}
</script>
