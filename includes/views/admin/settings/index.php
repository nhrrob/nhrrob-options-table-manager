<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly 
?>

<div class="wrap">
    <h1>Table Data</h1>

    <button id="add-button" class="button button-primary">Add New Option</button>

    <table id="data-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Option ID</th>
                <th>Option Name</th>
                <th>Option Value</th>
                <th>Autoload</th>
                <th>Action</th>
            </tr>
        </thead>
        
        <tbody>

        </tbody>
    </table>
</div>

<div id="add-modal" style="display:none;">
    <h2>Add New Option</h2>

    <label>Option Name: <input type="text" id="new-option-name"></label><br>
    <label>Option Value: <textarea id="new-option-value"></textarea></label><br>
    <label>Autoload:
        <select id="new-option-autoload">
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </label><br>

    <button id="save-option" class="button button-primary">Save</button>
</div>

<div id="edit-modal" style="display:none;">
    <h2>Edit Option</h2>
    
    <label>Option Name: <input type="text" id="edit-option-name" readonly></label><br>
    <label>Option Value: <textarea id="edit-option-value"></textarea></label><br>
    <label>Autoload:
        <select id="edit-option-autoload">
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </label><br>
    
    <button id="update-option" class="button button-primary">Update</button>
</div>