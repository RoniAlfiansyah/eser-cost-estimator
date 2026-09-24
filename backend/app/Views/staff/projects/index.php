<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="card">
    <span class="badge">Staff Module</span>
    <h2 style="margin: 16px 0 10px;">Project Workspace</h2>
    <p style="margin-top:0; color:#6b7280; line-height:1.7;">
        Halaman starter untuk operasional staff. Modul ini siap dikembangkan untuk input data proyek,
        item costing, material, tenaga kerja, overhead, dan upload dokumen pendukung.
    </p>

    <table class="table">
        <thead>
        <tr>
            <th>Fungsi</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Buat proyek baru</td>
            <td>Ready for development</td>
        </tr>
        <tr>
            <td>Input item costing</td>
            <td>Ready for development</td>
        </tr>
        <tr>
            <td>Upload lampiran</td>
            <td>Ready for development</td>
        </tr>
        </tbody>
    </table>
</section>
<?= $this->endSection() ?>
