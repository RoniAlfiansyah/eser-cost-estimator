<template>
  <div class="basic-cost-page">
    <b-breadcrumb>
      <b-breadcrumb-item>BASIC COST</b-breadcrumb-item>
      <b-breadcrumb-item active>Daftar Dokumen</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">Basic <span class="fw-semi-bold">Cost</span></h2>
        <p class="page-subtitle">Susun dokumen basic cost dari master data item biaya perusahaan.</p>
      </div>
      <b-button variant="primary" @click="$router.push('/app/basic-costs/create')">
        Buat Basic Cost
      </b-button>
    </div>

    <Widget title="<h5>Daftar <span class='fw-semi-bold'>Basic Cost</span></h5>" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <div class="table-responsive">
        <table class="table table-hover master-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Klien</th>
              <th>Proyek</th>
              <th>Status</th>
              <th>Lokasi</th>
              <th>Tanggal</th>
              <th>Jenis Pekerjaan</th>
              <th>Catatan Atasan</th>
              <th>Grand Total</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="10" class="text-center text-muted py-4">Memuat data basic cost...</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="10" class="text-center text-muted py-4">Belum ada dokumen basic cost.</td>
            </tr>
            <tr v-for="record in records" :key="record.id">
              <td>{{ record.id }}</td>
              <td>{{ record.client_name }}</td>
              <td>{{ record.project_name }}</td>
              <td>
                <span class="status-badge" :class="`status-${record.status}`">
                  {{ formatStatus(record.status) }}
                </span>
              </td>
              <td>{{ record.location || '-' }}</td>
              <td>{{ record.basic_cost_date }}</td>
              <td>{{ record.work_type_names && record.work_type_names.length ? record.work_type_names.join(', ') : '-' }}</td>
              <td>{{ record.review_notes || '-' }}</td>
              <td>{{ formatCurrency(record.grand_total) }}</td>
              <td>
                <div class="table-actions">
                  <b-button size="sm" variant="default" @click="$router.push(`/app/basic-costs/${record.id}/edit`)">
                    {{ record.can_edit ? 'Edit' : 'Buka' }}
                  </b-button>
                  <b-button size="sm" variant="danger" :disabled="!record.can_edit" @click="deleteRecord(record)">
                    Hapus
                  </b-button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Widget>
  </div>
</template>

<script>
import axios from 'axios';
import Widget from '@/components/Widget/Widget';

export default {
  name: 'BasicCostList',
  components: { Widget },
  data() {
    return {
      records: [],
      isLoading: false,
      errorMessage: '',
    };
  },
  methods: {
    loadRecords() {
      this.isLoading = true;
      this.errorMessage = '';

      axios.get('/basic-costs')
        .then((response) => {
          this.records = response.data.data || [];
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal memuat basic cost.';
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    deleteRecord(record) {
      if (!window.confirm(`Hapus dokumen basic cost untuk proyek "${record.project_name}"?`)) {
        return;
      }

      axios.delete(`/basic-costs/${record.id}`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'Basic cost berhasil dihapus.', { type: 'success' });
          this.loadRecords();
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal menghapus basic cost.';
        });
    },
    formatCurrency(value) {
      return `Rp ${Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    formatStatus(status) {
      const labels = {
        draft: 'Draft',
        submitted: 'Submitted',
        revision: 'Revision',
        approved: 'Approved',
      };

      return labels[status] || status;
    },
  },
  created() {
    this.loadRecords();
  },
};
</script>

<style src="./BasicCost.scss" lang="scss" scoped />
