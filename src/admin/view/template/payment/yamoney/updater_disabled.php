<div role="tabpanel" class="tab-pane" id="updater">
    <div class='row'>
        <div class='col-md-12'>
            <h4><?php echo $lang->get('updater_header'); ?></h4>
            <div class="form-horizontal">
                <div class="form-group">
                    <div class="col-sm-9 col-sm-offset-3" style="padding-left: 35px;">
                        <label class="checkbox" for="ya_updater_enable">
                            <input type="checkbox" name="ya_updater_enable" id="ya_updater_enable" value="1"
                                <?php echo ($ya_updater_enable ? 'checked' : ''); ?> />
                            <?php echo $lang->get('updater_enable'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>