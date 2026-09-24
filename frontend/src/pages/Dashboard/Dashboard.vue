<template>
  <div class="dashboard-page">
    <section class="dashboard-hero">
      <div class="hero-copy">
        <div class="hero-kicker">Dashboard</div>
        <h1 class="page-title">ESER Costing System</h1>
        <p class="page-subtitle">
          Ringkasan operasional basic cost, cost proposal, approval, dan master data yang aktif di sistem.
        </p>

        <div class="hero-highlights">
          <div class="highlight-pill">
            <span class="highlight-label">Basic Cost Approved</span>
            <strong>{{ countByStatus(basicCosts, 'approved') }}</strong>
          </div>
          <div class="highlight-pill">
            <span class="highlight-label">Proposal Issued</span>
            <strong>{{ countByStatus(proposals, 'issued') }}</strong>
          </div>
          <div class="highlight-pill">
            <span class="highlight-label">Menunggu Review</span>
            <strong>{{ pendingBasicCostReview + pendingProposalReview }}</strong>
          </div>
        </div>
      </div>

      <div class="hero-brand">
        <img :src="activeHeroLogo" alt="ESER Geosurvey" class="hero-logo">
      </div>
    </section>

    <b-alert variant="danger" :show="!!errorMessage" class="mb-4">{{ errorMessage }}</b-alert>

    <b-row class="summary-grid">
      <b-col xl="3" md="6" cols="12" v-for="card in summaryCards" :key="card.label">
        <Widget class="summary-widget" :fetchingData="isLoading">
          <div class="summary-card">
            <div class="summary-label">{{ card.label }}</div>
            <div class="summary-value">{{ card.value }}</div>
            <div class="summary-note">{{ card.note }}</div>
          </div>
        </Widget>
      </b-col>
    </b-row>

    <b-row>
      <b-col xl="8" cols="12">
        <Widget customHeader :title="`<h5>Pergerakan <span class='fw-semi-bold'>Status Dokumen</span></h5>`" :fetchingData="isLoading">
          <div class="status-section">
            <div class="status-group">
              <div class="section-title">Basic Cost</div>
              <div class="status-grid">
                <div class="status-card" v-for="item in basicCostStatusCards" :key="`bc-${item.label}`">
                  <div class="status-card-label">{{ item.label }}</div>
                  <div class="status-card-value">{{ item.value }}</div>
                </div>
              </div>
            </div>

            <div class="status-group">
              <div class="section-title">Cost Proposal</div>
              <div class="status-grid">
                <div class="status-card" v-for="item in proposalStatusCards" :key="`cp-${item.label}`">
                  <div class="status-card-label">{{ item.label }}</div>
                  <div class="status-card-value">{{ item.value }}</div>
                </div>
              </div>
            </div>
          </div>
        </Widget>
      </b-col>

      <b-col xl="4" cols="12">
        <Widget customHeader :title="`<h5>Aksi Cepat <span class='fw-semi-bold'>Tim Costing</span></h5>`" :fetchingData="isLoading">
          <div class="quick-actions">
            <router-link class="quick-action" :to="{ name: 'BasicCostCreatePage' }">
              <div class="quick-action-title">Buat Basic Cost</div>
              <div class="quick-action-note">Susun perhitungan biaya dasar proyek baru.</div>
            </router-link>

            <router-link class="quick-action" :to="{ name: 'CostProposalCreatePage' }">
              <div class="quick-action-title">Buat Cost Proposal</div>
              <div class="quick-action-note">Turunkan proposal komersial dari basic cost yang approved.</div>
            </router-link>

            <router-link class="quick-action" :to="{ name: 'AdminTemplatesListPage' }">
              <div class="quick-action-title">Kelola Template</div>
              <div class="quick-action-note">Rapikan template dokumen dan kebutuhan presentasi.</div>
            </router-link>
          </div>
        </Widget>
      </b-col>
    </b-row>

    <b-row>
      <b-col xl="4" cols="12">
        <Widget customHeader :title="`<h5>Prioritas <span class='fw-semi-bold'>Tindak Lanjut</span></h5>`" :fetchingData="isLoading">
          <div class="action-list">
            <div class="action-item">
              <div>
                <div class="action-title">Basic Cost menunggu review</div>
                <div class="action-caption">Dokumen submitted yang belum diputuskan.</div>
              </div>
              <div class="action-value">{{ pendingBasicCostReview }}</div>
            </div>

            <div class="action-item">
              <div>
                <div class="action-title">Cost Proposal menunggu review</div>
                <div class="action-caption">Proposal submitted yang perlu approval.</div>
              </div>
              <div class="action-value">{{ pendingProposalReview }}</div>
            </div>

            <div class="action-item">
              <div>
                <div class="action-title">Proposal siap diterbitkan</div>
                <div class="action-caption">Proposal approved yang siap di-issued ke client.</div>
              </div>
              <div class="action-value">{{ readyToIssueCount }}</div>
            </div>
          </div>
        </Widget>
      </b-col>

      <b-col xl="4" cols="12">
        <Widget customHeader :title="`<h5>Master Data <span class='fw-semi-bold'>Snapshot</span></h5>`" :fetchingData="isLoading">
          <div class="master-grid">
            <div class="master-card" v-for="item in masterDataCards" :key="item.label">
              <div class="master-card-label">{{ item.label }}</div>
              <div class="master-card-value">{{ item.value }}</div>
            </div>
          </div>
        </Widget>
      </b-col>

      <b-col xl="4" cols="12">
        <Widget customHeader :title="`<h5>Klien <span class='fw-semi-bold'>Paling Aktif</span></h5>`" :fetchingData="isLoading">
          <div class="client-list">
            <div v-if="topClients.length === 0" class="empty-state">Belum ada data klien untuk ditampilkan.</div>
            <div v-for="client in topClients" :key="client.name" class="client-item">
              <div>
                <div class="client-name">{{ client.name }}</div>
                <div class="client-meta">{{ client.documents }} dokumen proposal</div>
              </div>
              <div class="client-total">{{ formatCurrency(client.total) }}</div>
            </div>
          </div>
        </Widget>
      </b-col>
    </b-row>

    <b-row>
      <b-col xl="6" cols="12">
        <Widget customHeader :title="`<h5>Dokumen Basic Cost <span class='fw-semi-bold'>Terbaru</span></h5>`" :fetchingData="isLoading">
          <div class="table-responsive">
            <table class="table table-hover dashboard-table mb-0">
              <thead>
                <tr>
                  <th>Project</th>
                  <th>Client</th>
                  <th>Status</th>
                  <th class="text-end">Grand Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="recentBasicCosts.length === 0">
                  <td colspan="4" class="empty-row">Belum ada dokumen basic cost.</td>
                </tr>
                <tr v-for="item in recentBasicCosts" :key="`bc-${item.id}`">
                  <td>{{ item.project_name || '-' }}</td>
                  <td>{{ item.client_name || '-' }}</td>
                  <td>
                    <span class="status-badge" :class="`status-${item.status}`">{{ formatStatus(item.status) }}</span>
                  </td>
                  <td class="text-end">{{ formatCurrency(item.grand_total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Widget>
      </b-col>

      <b-col xl="6" cols="12">
        <Widget customHeader :title="`<h5>Dokumen Cost Proposal <span class='fw-semi-bold'>Terbaru</span></h5>`" :fetchingData="isLoading">
          <div class="table-responsive">
            <table class="table table-hover dashboard-table mb-0">
              <thead>
                <tr>
                  <th>No Proposal</th>
                  <th>Client</th>
                  <th>Status</th>
                  <th class="text-end">Grand Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="recentProposals.length === 0">
                  <td colspan="4" class="empty-row">Belum ada dokumen cost proposal.</td>
                </tr>
                <tr v-for="item in recentProposals" :key="`cp-${item.id}`">
                  <td>{{ item.proposal_number || '-' }}</td>
                  <td>{{ item.client_name || '-' }}</td>
                  <td>
                    <span class="status-badge" :class="`status-${item.status}`">{{ formatStatus(item.status) }}</span>
                  </td>
                  <td class="text-end">{{ formatCurrency(item.grand_total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Widget>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import axios from 'axios';
import { mapState } from 'vuex';
import Widget from '@/components/Widget/Widget';
import logoImage from '@/assets/company/eser-geosurvey-logo-white.png';
import logoImageDark from '@/assets/company/eser-geosurvey-logo.png';

export default {
  name: 'Dashboard',
  components: { Widget },
  data() {
    return {
      logoImage,
      logoImageDark,
      isLoading: false,
      errorMessage: '',
      basicCosts: [],
      proposals: [],
      masterCounts: {
        categories: 0,
        subcategories: 0,
        units: 0,
        templates: 0,
        signatories: 0,
      },
    };
  },
  computed: {
    ...mapState('layout', {
      sidebarColorName: state => state.sidebarColorName,
    }),
    activeHeroLogo() {
      return this.sidebarColorName === 'white' ? this.logoImageDark : this.logoImage;
    },
    summaryCards() {
      return [
        {
          label: 'Total Basic Cost',
          value: this.basicCosts.length,
          note: `${this.pendingBasicCostReview} dokumen menunggu review`,
        },
        {
          label: 'Total Cost Proposal',
          value: this.proposals.length,
          note: `${this.readyToIssueCount} proposal approved siap issued`,
        },
        {
          label: 'Nilai Basic Cost',
          value: this.formatCurrency(this.sumBy(this.basicCosts, 'grand_total')),
          note: `${this.countByStatus(this.basicCosts, 'approved')} dokumen sudah approved`,
        },
        {
          label: 'Nilai Cost Proposal',
          value: this.formatCurrency(this.sumBy(this.proposals, 'grand_total')),
          note: `${this.countByStatus(this.proposals, 'issued')} proposal sudah issued`,
        },
      ];
    },
    basicCostStatusCards() {
      return [
        { label: 'Draft', value: this.countByStatus(this.basicCosts, 'draft') },
        { label: 'Submitted', value: this.countByStatus(this.basicCosts, 'submitted') },
        { label: 'Revision', value: this.countByStatus(this.basicCosts, 'revision') },
        { label: 'Approved', value: this.countByStatus(this.basicCosts, 'approved') },
      ];
    },
    proposalStatusCards() {
      return [
        { label: 'Draft', value: this.countByStatus(this.proposals, 'draft') },
        { label: 'Submitted', value: this.countByStatus(this.proposals, 'submitted') },
        { label: 'Approved', value: this.countByStatus(this.proposals, 'approved') },
        { label: 'Issued', value: this.countByStatus(this.proposals, 'issued') },
      ];
    },
    masterDataCards() {
      return [
        { label: 'Kategori', value: this.masterCounts.categories },
        { label: 'Subkategori', value: this.masterCounts.subcategories },
        { label: 'Satuan', value: this.masterCounts.units },
        { label: 'Template', value: this.masterCounts.templates },
        { label: 'Penandatangan', value: this.masterCounts.signatories },
      ];
    },
    recentBasicCosts() {
      return this.sortByNewest(this.basicCosts).slice(0, 5);
    },
    recentProposals() {
      return this.sortByNewest(this.proposals).slice(0, 5);
    },
    pendingBasicCostReview() {
      return this.countByStatus(this.basicCosts, 'submitted');
    },
    pendingProposalReview() {
      return this.countByStatus(this.proposals, 'submitted');
    },
    readyToIssueCount() {
      return this.countByStatus(this.proposals, 'approved');
    },
    topClients() {
      const buckets = {};

      this.proposals.forEach((proposal) => {
        const name = (proposal.client_name || 'Tanpa Nama Klien').trim();
        if (!buckets[name]) {
          buckets[name] = {
            name,
            documents: 0,
            total: 0,
          };
        }

        buckets[name].documents += 1;
        buckets[name].total += Number(proposal.grand_total || 0);
      });

      return Object.values(buckets)
        .sort((a, b) => b.total - a.total)
        .slice(0, 5);
    },
  },
  methods: {
    async loadDashboard() {
      this.isLoading = true;
      this.errorMessage = '';

      try {
        const [
          basicCostsResponse,
          proposalsResponse,
          categoriesResponse,
          subcategoriesResponse,
          unitsResponse,
          templatesResponse,
          signatoriesResponse,
        ] = await Promise.all([
          axios.get('/basic-costs'),
          axios.get('/cost-proposals'),
          axios.get('/categories'),
          axios.get('/subcategories'),
          axios.get('/units'),
          axios.get('/templates'),
          axios.get('/signatories'),
        ]);

        this.basicCosts = basicCostsResponse.data.data || [];
        this.proposals = proposalsResponse.data.data || [];
        this.masterCounts = {
          categories: (categoriesResponse.data.data || []).length,
          subcategories: (subcategoriesResponse.data.data || []).length,
          units: (unitsResponse.data.data || []).length,
          templates: (templatesResponse.data.data || []).length,
          signatories: (signatoriesResponse.data.data || []).length,
        };
      } catch (error) {
        this.errorMessage = this.getErrorMessage(error, 'Dashboard gagal dimuat.');
      } finally {
        this.isLoading = false;
      }
    },
    countByStatus(records, status) {
      return records.filter((item) => (item.status || '').toLowerCase() === status).length;
    },
    sumBy(records, key) {
      return records.reduce((total, item) => total + Number(item[key] || 0), 0);
    },
    sortByNewest(records) {
      return [...records].sort((a, b) => {
        const left = new Date(a.updated_at || a.created_at || 0).getTime();
        const right = new Date(b.updated_at || b.created_at || 0).getTime();
        return right - left;
      });
    },
    formatCurrency(value) {
      return `Rp ${Number(value || 0).toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
    },
    formatStatus(status) {
      const value = (status || '-').replace(/_/g, ' ');
      return value.charAt(0).toUpperCase() + value.slice(1);
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
  },
  mounted() {
    this.loadDashboard();
  },
};
</script>

<style src="./Dashboard.scss" lang="scss" />
