<tr class="inline-edit-row quick-edit-row alternate inline-editor delete" id="delete-1">
    <td class="colspanchange" colspan="<?php echo $columns ?>">
        <form action="<?php echo admin_url('admin-ajax.php') ?>" method="post">
        <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
                <label>
                    <span class="title delete-title" style="width: 100%">Are you sure you want to delete this item?</span>
                </label>
        </fieldset>

        <p class="submit inline-edit-save">
            <a class="button-secondary cancel alignleft" title="Cancel" href="#inline-edit" accesskey="c">Cancel</a>
            <a class="button-primary delete alignright" title="Delete" href="#inline-edit" accesskey="s">Delete</a>
            <img alt="" src="http://local.wordpress.org/wp-admin/images/wpspin_light.gif" style="display: none;" class="waiting">
            <input type="hidden" value="<?php echo $_POST['id'] ?>" name="id">
            <input type="hidden" value="<?php echo $_POST['action'] ?>" name="action">
            <br class="clear">
        </p>
        </form>
    </td>
</tr>