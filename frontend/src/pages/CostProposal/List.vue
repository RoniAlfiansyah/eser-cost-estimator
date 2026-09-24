<template>
  <div class="cost-proposal-page">
    <b-breadcrumb>
      <b-breadcrumb-item>COST PROPOSAL</b-breadcrumb-item>
      <b-breadcrumb-item active>Daftar Proposal</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">Cost <span class="fw-semi-bold">Proposal</span></h2>
        <p class="page-subtitle">Buat proposal komersial dari basic cost yang sudah approved.</p>
      </div>
      <b-button variant="primary" @click="$router.push('/app/cost-proposals/create')">
        Buat Cost Proposal
      </b-button>
    </div>

    <Widget title="<h5>Daftar <span class='fw-semi-bold'>Cost Proposal</span></h5>" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <div class="table-responsive">
        <table class="table table-hover proposal-table">
          <thead>
            <tr>
              <th>No Proposal</th>
              <th>Klien</th>
              <th>Proyek</th>
              <th>Ref Basic Cost</th>
              <th>Status</th>
              <th>Tanggal Proposal</th>
              <th>Grand Total</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="8" class="text-center py-4">Memuat data cost proposal...</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="8" class="text-center py-4">Belum ada cost proposal.</td>
            </tr>
            <tr v-for="record in records" :key="record.id">
              <td>{{ record.proposal_number }}</td>
              <td>{{ record.client_name }}</td>
              <td>{{ record.project_name }}</td>
              <td>#{{ record.basic_cost_id }}</td>
              <td><span class="status-badge" :class="`status-${record.status}`">{{ formatStatus(record.status) }}</span></td>
              <td>{{ record.proposal_date }}</td>
              <td>{{ formatCurrency(record.grand_total) }}</td>
              <td>
                <div class="table-actions">
                  <b-button size="sm" variant="default" @click="$router.push(`/app/cost-proposals/${record.id}/edit`)">
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
  name: 'CostProposalList',
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
      axios.get('/cost-proposals')
        .then((response) => {
          this.records = response.data.data || [];
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal memuat cost proposal.');
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    deleteRecord(record) {
      if (!window.confirm(`Hapus cost proposal "${record.proposal_number}"?`)) {
        return;
      }

      axios.delete(`/cost-proposals/${record.id}`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'Cost proposal berhasil dihapus.', { type: 'success' });
          this.loadRecords();
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal menghapus cost proposal.');
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
        issued: 'Issued',
        cancelled: 'Cancelled',
      };

      return labels[status] || status;
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
  },
  created() {
    this.loadRecords();
  },
};
</script>

<style src="./CostProposal.scss" lang="scss" scoped />
