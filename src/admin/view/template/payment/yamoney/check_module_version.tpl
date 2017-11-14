<?php echo $header; ?>
<link rel="stylesheet" href="https://yastatic.net/bootstrap/3.3.6/css/bootstrap.min.css">
<div id="content" class="container">
    <div class="row">
        <div class="col-12">
            <h4>Проверка наличия новой версии модуля</h4>
        </div>
    </div>

    <ul>
        <li>Установленная версия модуля: <?php echo $currentVersion; ?></li>
        <li>Последняя доступная версия модуля: <?php echo $newVersion; ?></li>
    </ul>

    <?php if ($new_version_available) : ?>

    <h5>Изменения в последней версии модуля:</h5>
    <p><?php echo $changelog; ?></p>

    <?php else: ?>
    <p>Установлена последняя версия модуля.</p>
    <?php endif; ?>
</div>
<?php echo $footer; ?>