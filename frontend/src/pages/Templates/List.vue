<template>
  <div class="template-page">
    <b-breadcrumb>
      <b-breadcrumb-item>TEMPLATE</b-breadcrumb-item>
      <b-breadcrumb-item active>Daftar Template</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">Daftar <span class="fw-semi-bold">Template</span></h2>
        <p class="page-subtitle">Kelola template item master yang akan dipakai untuk mempercepat pembuatan basic cost.</p>
      </div>
      <b-button variant="primary" @click="$router.push('/app/admin/templates/create')">
        Buat Template
      </b-button>
    </div>

    <Widget title="<h5>Filter <span class='fw-semi-bold'>Template</span></h5>" customHeader class="mb-xlg">
      <b-row>
        <b-col md="5">
          <b-form-group label="Cari Template">
            <b-form-input v-model="filters.q" placeholder="Cari nama template atau deskripsi" />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group label="Jenis Pekerjaan">
            <b-form-select v-model="filters.work_type_id" :options="workTypeOptions" />
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group label="Status">
            <b-form-select v-model="filters.is_active" :options="statusOptions" />
          </b-form-group>
        </b-col>
      </b-row>
      <div class="form-actions compact">
        <b-button variant="primary" @click="loadTemplates">Filter</b-button>
        <b-button variant="inverse" @click="resetFilters">Reset</b-button>
      </div>
    </Widget>

    <Widget title="<h5>Daftar <span class='fw-semi-bold'>Template</span></h5>" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <div class="table-responsive">
        <table class="table table-hover template-table">
          <thead>
            <tr>
              <th>Jenis Pekerjaan</th>
              <th>Nama Template</th>
              <th>Deskripsi</th>
              <th>Jumlah Item</th>
              <th>Status</th>
              <th>Updated</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="7" class="text-center py-4">Memuat data...</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="7" class="text-center py-4">Belum ada template tersimpan.</td>
            </tr>
            <tr v-for="record in records" :key="record.id">
              <td>{{ record.work_type_name || '-' }}</td>
              <td>{{ record.template_name }}</td>
              <td>{{ record.description || '-' }}</td>
              <td>{{ record.item_count }}</td>
              <td>
                <span class="status-badge" :class="record.is_active ? 'active' : 'inactive'">
                  {{ record.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>{{ record.updated_at || '-' }}</td>
              <td>
                <div class="table-actions">
                  <b-button size="sm" variant="default" @click="$router.push(`/app/admin/templates/${record.id}/edit`)">Edit</b-button>
                  <b-button size="sm" variant="danger" @click="deleteRecord(record)">Hapus</b-button>
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
  name: 'TemplateListPage',
  components: { Widget },
  data() {
    return {
      records: [],
      errorMessage: '',
      isLoading: false,
      filters: {
        q: '',
        work_type_id: '',
        is_active: '',
      },
      workTypeOptions: [{ value: '', text: 'Semua Jenis Pekerjaan' }],
      statusOptions: [
        { value: '', text: 'Semua Status' },
        { value: '1', text: 'Active' },
        { value: '0', text: 'Inactive' },
      ],
    };
  },
  methods: {
    loadReferences() {
      return axios.get('/categories')
        .then((response) => {
          const options = (response.data.data || []).map((item) => ({
            value: String(item.id),
            text: item.name,
          }));
          this.workTypeOptions = [{ value: '', text: 'Semua Jenis Pekerjaan' }].concat(options);
        });
    },
    loadTemplates() {
      this.isLoading = true;
      this.errorMessage = '';

      const params = {};
      if (this.filters.q) {
        params.q = this.filters.q;
      }
      if (this.filters.work_type_id) {
        params.work_type_id = this.filters.work_type_id;
      }
      if (this.filters.is_active !== '') {
        params.is_active = this.filters.is_active;
      }

      axios.get('/templates', { params })
        .then((response) => {
          this.records = response.data.data || [];
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal memuat data template.');
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    resetFilters() {
      this.filters = {
        q: '',
        work_type_id: '',
        is_active: '',
      };
      this.loadTemplates();
    },
    deleteRecord(record) {
      if (!window.confirm(`Hapus template "${record.template_name}"?`)) {
        return;
      }

      axios.delete(`/templates/${record.id}`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'Template berhasil dihapus.', { type: 'success' });
          this.loadTemplates();
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal menghapus template.');
        });
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
  },
  created() {
    this.loadReferences().then(() => this.loadTemplates());
  },
};
</script>

<style src="./Template.scss" lang="scss" scoped />
