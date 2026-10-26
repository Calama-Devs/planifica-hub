<?= $this->extend('layouts/principal') ?>

<?= $this->section('titulo') ?><?= esc($title ?? 'Dashboard') ?><?= $this->endSection() ?>

<?= $this->section('conteudo') ?>
<?php if (isset($content)) : ?>
    <?= $content ?>
<?php endif; ?>
<?= $this->endSection() ?>
