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

    <div class='row'>
        <div class='col-md-12'>
            <h4>Версия модуля:</h4>
            <ul>
                <li>Установленная версия модуля: <?php echo $currentVersion; ?></li>
                <li>Последняя доступная версия модуля: <?php echo $newVersion; ?></li>
                <li>
                    Дата проверки наличия новой версии: <?php echo $newVersionInfo['date'] ?>
                    <?php if (time() - $newVersionInfo['time'] > 300) : ?>
                        <button type="button" class="btn btn-success btn-xs" id="force-check">Проверить наличие обновлений</button>
                    <?php endif; ?>
                </li>
            </ul>

            <?php if ($new_version_available) : ?>

                <h4>История изменений:</h4>
                <p><?php echo $changelog; ?></p>
                <button type="button" id="update-module" class="btn btn-primary">Обновить модуль</button>
                <form method="post" id="update-form" action="<?php echo $update_action; ?>">
                    <input name="update" value="1" type="hidden" />
                    <input name="version" value="<?php echo htmlspecialchars($newVersion) ?>" type="hidden" />
                </form>

            <?php else: ?>
                <p>Установлена последняя версия модуля.</p>
            <?php endif; ?>

            <form method="post" id="check-version" action="<?php echo $update_action; ?>">
                <input name="force" value="1" type="hidden" />
            </form>
        </div>
    </div>

    <div class="row">
        <h4>Бэкапы модуля</h4>
        <?php if (empty($backups)) : ?>
            <p>Не найдено ни одного бэкапа.</p>
        <?php else: ?>

            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th>Версия модуля</th>
                    <th>Дата создания</th>
                    <th>Имя файла</th>
                    <th>Размер файла бэкапа</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $backup) : ?>
                    <tr>
                        <td><?php echo $backup['version'] ?></td>
                        <td><?php echo $backup['date'] ?></td>
                        <td><?php echo $backup['name'] ?></td>
                        <td><?php echo $backup['size'] ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-success btn-xs restore-backup" data-id="<?php echo $backup['name'] ?>" data-version="<?php echo $backup['version'] ?>" data-date="<?php echo $backup['date'] ?>">Восстановить</button>
                            <button type="button" class="btn btn-danger btn-xs remove-backup" data-id="<?php echo $backup['name'] ?>" data-version="<?php echo $backup['version'] ?>" data-date="<?php echo $backup['date'] ?>">Удалить</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

        <form id="action-form" method="post" action="<?php echo $backup_action; ?>">
            <input type="hidden" name="action" id="action-form-action" value="none" />
            <input type="hidden" name="file_name" id="action-form-file-name" value="" />
            <input type="hidden" name="version" id="action-form-version" value="" />
        </form>
    </div>

</div>

<script type="text/javascript">
    jQuery(document).ready(function () {
        jQuery('button.restore-backup').click(function () {
            var message = 'Вы действительно хотите восстановить модуль из бэкапа версии ' + this.dataset.version
                + ' от ' + this.dataset.date + '?';
            if (confirm(message)) {
                jQuery('#action-form-action').val('restore');
                jQuery('#action-form-file-name').val(this.dataset.id);
                jQuery('#action-form-version').val(this.dataset.version);
                jQuery('#action-form').submit();
            }
        });
        jQuery('button.remove-backup').click(function () {
            var message = 'Вы действительно хотите удалить бэкап модуля версии ' + this.dataset.version
                + ' от ' + this.dataset.date + '?';
            if (confirm(message)) {
                jQuery('#action-form-action').val('remove');
                jQuery('#action-form-file-name').val(this.dataset.id);
                jQuery('#action-form-version').val(this.dataset.version);
                jQuery('#action-form').submit();
            }
        });
        jQuery('#force-check').click(function () {
            jQuery('#check-version')[0].submit();
        });
        <?php if ($new_version_available) : ?>
        jQuery('#update-module').click(function () {
            jQuery('#update-form')[0].submit();
        });
        <?php endif; ?>
    });
</script>