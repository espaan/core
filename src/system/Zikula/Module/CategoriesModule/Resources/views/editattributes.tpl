<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>{gt text='Name'}</th>
            <th>{gt text='Value'}</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><input type="text" name="attribute_name[]" id="new_attribute_name" value="" /></td>
            <td><input type="text" name="attribute_value[]" id="new_attribute_value" value="" size="50" /></td>
            <td><input type="image" id="category_attributes_add" title="{gt text='Add'}" src="{$baseurl}images/icons/extrasmall/edit_add.png"/></td>
        </tr>
        {foreach key='name' item='value' from=$attributes}
        <tr>
            <td><input type="text" name="attribute_name[]" value="{$name}" /></td>
            <td><input type="text" name="attribute_value[]" value="{$value}" size="50" /></td>
            <td><input type="image" class="category_attributes_remove" title="{gt text='Delete'}" src="{$baseurl}images/icons/extrasmall/edit_remove.png"/></td>
        </tr>
        {/foreach}
    </tbody>
</table>
