<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="card">
    <span class="badge">Manager Module</span>
    <h2 style="margin: 16px 0 10px;">Approval Center</h2>
    <p style="margin-top:0; color:#6b7280; line-height:1.7;">
        Halaman starter untuk alur review dan approval costing. Modul ini cocok dikembangkan
        untuk approval estimasi, approval revisi budget, dan status persetujuan proyek.
    </p>

    <table class="table">
        <thead>
        <tr>
            <th>Queue</th>
            <th>Deskripsi</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Cost Review</td>
            <td>Review draft costing sebelum dikirim ke client</td>
            <td>Ready for development</td>
        </tr>
        <tr>
            <td>Budget Change</td>
            <td>Persetujuan revisi anggaran proyek</td>
            <td>Ready for development</td>
        </tr>
        </tbody>
    </table>
</section>
<?= $this->endSection() ?>
