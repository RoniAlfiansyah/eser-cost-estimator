<template>
  <div class="basic-cost-page">
    <b-breadcrumb>
      <b-breadcrumb-item>BASIC COST</b-breadcrumb-item>
      <b-breadcrumb-item to="/app/basic-costs">Daftar Dokumen</b-breadcrumb-item>
      <b-breadcrumb-item active>{{ isEditMode ? 'Edit Basic Cost' : 'Buat Basic Cost' }}</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">{{ isEditMode ? 'Edit' : 'Buat' }} <span class="fw-semi-bold">Basic Cost</span></h2>
        <p class="page-subtitle">Pilih item dari master data, atur quantity/duration/unit price, lalu sistem akan menghitung total otomatis.</p>
      </div>
      <div class="page-header-actions">
        <b-button
          v-if="isEditMode"
          variant="success"
          :disabled="isSubmitting"
          @click="exportDetail"
        >
          Export Detail
        </b-button>
        <b-button variant="default" @click="$router.push('/app/basic-costs')">
          Kembali
        </b-button>
      </div>
    </div>

    <Widget title="<h5>Header <span class='fw-semi-bold'>Project</span></h5>" customHeader class="mb-xlg">
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <b-form>
        <b-row>
          <b-col md="4">
            <b-form-group label="Nama Klien">
              <b-form-input v-model="form.client_name" :disabled="!canEditDocument" required placeholder="Masukkan nama klien" />
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group label="Nama Proyek">
              <b-form-input v-model="form.project_name" :disabled="!canEditDocument" required placeholder="Masukkan nama proyek" />
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group label="Lokasi">
              <b-form-input v-model="form.location" :disabled="!canEditDocument" placeholder="Masukkan lokasi pekerjaan" />
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group label="Tanggal">
              <b-form-input v-model="form.basic_cost_date" :disabled="!canEditDocument" type="date" required />
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group label="Jenis Pekerjaan">
              <div class="work-type-checkboxes">
                <b-form-checkbox
                  v-for="option in workTypeOptions"
                  :key="option.value"
                  v-model="form.work_type_category_ids"
                  :disabled="!canEditDocument"
                  :value="option.value"
                  :unchecked-value="null"
                >
                  {{ option.text }}
                </b-form-checkbox>
              </div>
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group label="Catatan">
              <b-form-textarea v-model="form.notes" :disabled="!canEditDocument" rows="2" placeholder="Catatan tambahan basic cost" />
            </b-form-group>
          </b-col>
        </b-row>
      </b-form>
    </Widget>

    <Widget v-if="isEditMode" title="<h5>Approval <span class='fw-semi-bold'>Basic Cost</span></h5>" customHeader class="mb-xlg">
      <div class="approval-summary">
        <div class="approval-meta">
          <span class="status-badge" :class="statusClass">{{ statusLabel }}</span>
          <span v-if="documentMeta.submitted_at" class="approval-meta-text">
            Diajukan {{ documentMeta.submitted_at }} oleh {{ documentMeta.submitted_by_name || 'pengguna' }}
          </span>
          <span v-if="documentMeta.reviewed_at" class="approval-meta-text">
            Direview {{ documentMeta.reviewed_at }} oleh {{ documentMeta.reviewed_by_name || 'atasan' }}
          </span>
        </div>
        <div v-if="documentMeta.review_notes" class="approval-note-box">
          <strong>Catatan Atasan:</strong> {{ documentMeta.review_notes }}
        </div>
      </div>

      <b-form-group
        v-if="canRequestRevisionDocument || canApproveDocument"
        label="Catatan Review"
        class="mt-md"
      >
        <b-form-textarea
          v-model="approvalActionNote"
          rows="3"
          placeholder="Tulis catatan approval atau revisi untuk pembuat dokumen"
        />
      </b-form-group>

      <div class="form-actions">
        <b-button v-if="canSubmitDocument" variant="warning" @click="submitForReview">
          Submit for Review
        </b-button>
        <b-button v-if="canRequestRevisionDocument" variant="danger" @click="requestRevision">
          Request Revision
        </b-button>
        <b-button v-if="canApproveDocument" variant="success" @click="approveDocument">
          Approve
        </b-button>
      </div>

      <div v-if="approvalHistory.length" class="approval-history mt-lg">
        <h6 class="fw-semi-bold mb-md">Riwayat Approval</h6>
        <div v-for="item in approvalHistory" :key="item.id" class="approval-history-item">
          <div class="approval-history-top">
            <strong>{{ formatHistoryAction(item.action) }}</strong>
            <span>{{ item.created_at || '-' }}</span>
          </div>
          <div class="approval-history-meta">
            <span>{{ item.actor_name || 'Sistem' }}</span>
            <span>{{ item.status_from || '-' }} -> {{ item.status_to }}</span>
          </div>
          <p v-if="item.note" class="approval-history-note">{{ item.note }}</p>
        </div>
      </div>
    </Widget>

    <Widget title="<h5>Load <span class='fw-semi-bold'>Template</span></h5>" customHeader class="mb-xlg">
      <b-row>
        <b-col md="8">
          <b-form-group label="Pilih Template Berdasarkan Jenis Pekerjaan">
            <b-form-select
              v-model="selectedTemplateId"
              :options="templateOptions"
              :disabled="availableTemplates.length === 0 || !canEditDocument"
            />
          </b-form-group>
        </b-col>
        <b-col md="4" class="d-flex align-items-end">
          <b-button
            variant="primary"
            class="w-100"
            :disabled="!selectedTemplateId || isLoadingTemplateDetail || !canEditDocument"
            @click="applyTemplate"
          >
            {{ isLoadingTemplateDetail ? 'Memuat Template...' : 'Load Template' }}
          </b-button>
        </b-col>
      </b-row>
      <small class="text-muted">
        Pilih minimal satu jenis pekerjaan terlebih dahulu. Template yang dimuat akan menambahkan item ke tabel basic cost dan tetap bisa Anda edit lagi.
      </small>
    </Widget>

    <Widget title="<h5>Tambahkan <span class='fw-semi-bold'>Item Master</span></h5>" customHeader class="mb-xlg">
      <b-row>
        <b-col md="4">
          <b-form-group label="Filter Kategori">
            <b-form-select v-model="picker.category_id" :disabled="!canEditDocument" :options="categoryFilterOptions" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Pilih Item Master">
            <b-form-select v-model="picker.cost_item_id" :disabled="!canEditDocument" :options="masterItemOptions" />
          </b-form-group>
        </b-col>
        <b-col md="2" class="d-flex align-items-end">
          <b-button variant="primary" class="w-100" :disabled="!canEditDocument" @click="addMasterItem">
            Tambah Item
          </b-button>
        </b-col>
      </b-row>
    </Widget>

    <Widget title="<h5>Tabel <span class='fw-semi-bold'>Item Basic Cost</span></h5>" customHeader>
      <div class="table-responsive">
        <table class="table table-hover basic-cost-table">
          <thead>
            <tr>
              <th>Kategori</th>
              <th>Item</th>
              <th>Satuan</th>
              <th>Quantity</th>
              <th>Duration</th>
              <th>Unit Price</th>
              <th>Total</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="lineItems.length === 0">
              <tr>
                <td colspan="8" class="text-center text-muted py-4">Belum ada item basic cost. Tambahkan dari master data di atas.</td>
              </tr>
            </template>
            <template v-else>
              <template v-for="row in displayRows">
                <tr v-if="row.type === 'item'" :key="`item-${row.item.id}`">
                  <td>{{ row.item.category_name }}</td>
                  <td>
                    {{ row.item.item_name }}
                    <b-badge v-if="row.item.source_type === 'schedule'" variant="info" class="ml-2">Dari Schedule</b-badge>
                  </td>
                  <td>{{ row.item.unit_symbol || '-' }}</td>
                  <td>
                    <b-form-input v-model="row.item.quantity" :disabled="!canEditDocument" type="number" step="1" min="0" @input="normalizeLine(row.item)" />
                  </td>
                  <td>
                    <b-form-input v-model="row.item.duration" :disabled="!canEditDocument" type="number" step="1" min="0" @input="normalizeLine(row.item)" />
                  </td>
                  <td>
                    <b-form-input
                      :value="displayUnitPrice(row.item)"
                      :disabled="!canEditDocument"
                      type="text"
                      inputmode="decimal"
                      @focus="activeUnitPriceRowId = row.item.id"
                      @blur="handleUnitPriceBlur()"
                      @input="updateUnitPrice(row.item, $event)"
                    />
                  </td>
                  <td>{{ formatCurrency(lineTotal(row.item)) }}</td>
                  <td>
                    <div class="table-actions">
                      <b-button size="sm" variant="danger" :disabled="!canEditDocument" @click="removeLine(row.item.id)">Hapus</b-button>
                    </div>
                  </td>
                </tr>
                <tr v-else :key="`subtotal-${row.category_name}`" class="subtotal-row">
                  <td colspan="6" class="text-end fw-semi-bold">Subtotal {{ row.category_name }}</td>
                  <td class="fw-semi-bold">{{ formatCurrency(row.total) }}</td>
                  <td></td>
                </tr>
              </template>
            </template>
          </tbody>
          <tfoot v-if="lineItems.length > 0">
            <tr class="grand-total-row">
              <td colspan="6" class="text-end">Grand Total</td>
              <td>{{ formatCurrency(grandTotal) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="form-actions mt-lg">
        <b-button variant="primary" :disabled="isSubmitting || !canEditDocument" @click="submitForm">
          {{ isSubmitting ? 'Menyimpan...' : (isEditMode ? 'Update Basic Cost' : 'Simpan Basic Cost') }}
        </b-button>
        <b-button variant="inverse" class="ms-2" @click="$router.push('/app/basic-costs')">
          Batal
        </b-button>
      </div>
    </Widget>
  </div>
</template>

<script>
import axios from 'axios';
import ExcelJS from 'exceljs';
import Widget from '@/components/Widget/Widget';

const categoryOrder = [
  'Transportasi, Akomodasi & Konsumsi',
  'Hydrography Survey',
  'Oceanography Survey',
  'Geotechnical Investigation',
  'Aerial Photogrammetry / LiDAR Survey',
  'Topography Survey',
  'Geophysical Survey',
  'Personnel',
  'Processing & Reporting',
];

function sortCategoryNames(names) {
  return names.sort((a, b) => {
    const aOrder = categoryOrder.indexOf(a);
    const bOrder = categoryOrder.indexOf(b);
    const aRank = aOrder === -1 ? 6.5 : aOrder;
    const bRank = bOrder === -1 ? 6.5 : bOrder;
    return aRank - bRank;
  });
}

export default {
  name: 'BasicCostForm',
  components: { Widget },
  data() {
    return {
      form: {
        client_name: '',
        project_name: '',
        location: '',
        basic_cost_date: new Date().toISOString().slice(0, 10),
        work_type_category_ids: [],
        notes: '',
      },
      picker: {
        category_id: '',
        cost_item_id: '',
      },
      categoryOptions: [{ value: '', text: 'Pilih Jenis Pekerjaan' }],
      categoryFilterOptions: [{ value: '', text: 'Semua Kategori' }],
      masterItems: [],
      availableTemplates: [],
      selectedTemplateId: '',
      lineItems: [],
      activeUnitPriceRowId: null,
      isSubmitting: false,
      isLoadingTemplates: false,
      isLoadingTemplateDetail: false,
      errorMessage: '',
      approvalActionNote: '',
      approvalHistory: [],
      documentMeta: {
        status: 'draft',
        submitted_at: null,
        submitted_by_name: null,
        reviewed_at: null,
        reviewed_by_name: null,
        review_notes: null,
      },
      permissionFlags: {
        can_edit: true,
        can_submit: false,
        can_request_revision: false,
        can_approve: false,
      },
      nextLineId: 1,
    };
  },
  computed: {
    isEditMode() {
      return !!this.$route.params.id;
    },
    filteredMasterItems() {
      if (!this.picker.category_id) {
        return this.masterItems;
      }

      return this.masterItems.filter((item) => String(item.category_id) === this.picker.category_id);
    },
    masterItemOptions() {
      const options = this.filteredMasterItems.map((item) => ({
        value: String(item.id),
        text: `${item.category_name} - ${item.item_name} (${item.unit_symbol || '-'})`,
      }));

      return [{ value: '', text: 'Pilih item master' }].concat(options);
    },
    workTypeOptions() {
      return this.categoryOptions.filter((option) => option.value !== '');
    },
    templateOptions() {
      const options = this.availableTemplates.map((template) => ({
        value: String(template.id),
        text: `${template.work_type_name || '-'} - ${template.template_name}`,
      }));

      if (this.form.work_type_category_ids.length === 0) {
        return [{ value: '', text: 'Pilih jenis pekerjaan terlebih dahulu' }];
      }

      if (options.length === 0) {
        return [{ value: '', text: 'Belum ada template aktif untuk jenis pekerjaan ini' }];
      }

      return [{ value: '', text: 'Pilih template' }].concat(options);
    },
    groupedSubtotals() {
      return this.lineItems.reduce((groups, item) => {
        const key = item.category_name || '-';
        if (!groups[key]) {
          groups[key] = 0;
        }

        groups[key] += this.lineTotal(item);
        return groups;
      }, {});
    },
    displayRows() {
      const rows = [];
      const grouped = {};

      this.lineItems.forEach((item) => {
        const key = item.category_name || '-';
        if (!grouped[key]) {
          grouped[key] = [];
        }
        grouped[key].push(item);
      });

      sortCategoryNames(Object.keys(grouped)).forEach((categoryName) => {
        grouped[categoryName].forEach((item) => rows.push({ type: 'item', item }));
        rows.push({
          type: 'subtotal',
          category_name: categoryName,
          total: grouped[categoryName].reduce((sum, item) => sum + this.lineTotal(item), 0),
        });
      });

      return rows;
    },
    grandTotal() {
      return this.lineItems.reduce((sum, item) => sum + this.lineTotal(item), 0);
    },
    canEditDocument() {
      return !this.isEditMode || this.permissionFlags.can_edit;
    },
    canSubmitDocument() {
      return this.isEditMode && this.permissionFlags.can_submit;
    },
    canRequestRevisionDocument() {
      return this.isEditMode && this.permissionFlags.can_request_revision;
    },
    canApproveDocument() {
      return this.isEditMode && this.permissionFlags.can_approve;
    },
    statusLabel() {
      const map = {
        draft: 'Draft',
        submitted: 'Submitted',
        revision: 'Revision',
        approved: 'Approved',
      };

      return map[this.documentMeta.status] || 'Draft';
    },
    statusClass() {
      return `status-${this.documentMeta.status || 'draft'}`;
    },
  },
  methods: {
    loadReferences() {
      return Promise.all([
        axios.get('/categories').then((response) => {
          const options = (response.data.data || []).map((item) => ({
            value: String(item.id),
            text: item.name,
          }));
          this.categoryOptions = [{ value: '', text: 'Pilih Jenis Pekerjaan' }].concat(options);
          this.categoryFilterOptions = [{ value: '', text: 'Semua Kategori' }].concat(options);
        }),
        axios.get('/cost-items').then((response) => {
          this.masterItems = response.data.data || [];
        }),
      ]);
    },
    loadDocument() {
      if (!this.isEditMode) {
        return;
      }

      axios.get(`/basic-costs/${this.$route.params.id}`)
        .then((response) => {
          const data = response.data.data;
          this.form.client_name = data.client_name;
          this.form.project_name = data.project_name;
          this.form.location = data.location || '';
          this.form.basic_cost_date = data.basic_cost_date;
          this.form.work_type_category_ids = (data.work_type_category_ids || []).map((id) => String(id));
          this.form.notes = data.notes || '';
          this.permissionFlags = {
            can_edit: !!data.can_edit,
            can_submit: !!data.can_submit,
            can_request_revision: !!data.can_request_revision,
            can_approve: !!data.can_approve,
          };
          this.documentMeta = {
            status: data.status || 'draft',
            submitted_at: data.submitted_at || null,
            submitted_by_name: data.submitted_by_name || null,
            reviewed_at: data.reviewed_at || null,
            reviewed_by_name: data.reviewed_by_name || null,
            review_notes: data.review_notes || null,
          };
          this.approvalHistory = data.approval_history || [];
          this.lineItems = (data.items || []).map((item) => ({
            id: this.generateLineId(),
            cost_item_id: item.cost_item_id,
            category_id: item.category_id,
            category_name: item.category_name,
            subcategory_id: item.subcategory_id,
            subcategory_name: item.subcategory_name,
            item_name: item.item_name,
            unit_id: item.unit_id,
            unit_symbol: item.unit_symbol,
            quantity: String(item.quantity),
            duration: String(item.duration),
            unit_price: String(item.unit_price),
            source_type: item.source_type || null,
            source_key: item.source_key || null,
          }));
          this.loadTemplates();
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal memuat dokumen basic cost.';
        });
    },
    addMasterItem() {
      const selected = this.masterItems.find((item) => String(item.id) === this.picker.cost_item_id);
      if (!selected) {
        this.$toasted.show('Pilih item master terlebih dahulu.', { type: 'error' });
        return;
      }

      this.lineItems.push({
        id: this.generateLineId(),
        cost_item_id: selected.id,
        category_id: selected.category_id,
        category_name: selected.category_name,
        subcategory_id: selected.subcategory_id,
        subcategory_name: selected.subcategory_name,
        item_name: selected.item_name,
        unit_id: selected.unit_id,
        unit_symbol: selected.unit_symbol,
        quantity: '1',
        duration: String(selected.default_duration || 1),
        unit_price: String(selected.default_price || 0),
      });

      this.picker.cost_item_id = '';
    },
    loadTemplates() {
      if (this.form.work_type_category_ids.length === 0) {
        this.availableTemplates = [];
        this.selectedTemplateId = '';
        return;
      }

      this.isLoadingTemplates = true;

      axios.get('/templates', {
        params: {
          work_type_ids: this.form.work_type_category_ids.join(','),
        },
      }).then((response) => {
        this.availableTemplates = response.data.data || [];
        if (!this.availableTemplates.find((item) => String(item.id) === this.selectedTemplateId)) {
          this.selectedTemplateId = '';
        }
      }).catch(() => {
        this.availableTemplates = [];
        this.selectedTemplateId = '';
      }).finally(() => {
        this.isLoadingTemplates = false;
      });
    },
    applyTemplate() {
      if (!this.selectedTemplateId) {
        this.$toasted.show('Pilih template terlebih dahulu.', { type: 'error' });
        return;
      }

      this.isLoadingTemplateDetail = true;

      axios.get(`/templates/${this.selectedTemplateId}`)
        .then((response) => {
          const template = response.data.data;
          const rows = (template.items || []).map((item) => ({
            id: this.generateLineId(),
            cost_item_id: item.cost_item_id,
            category_id: item.category_id,
            category_name: item.category_name,
            subcategory_id: item.subcategory_id,
            subcategory_name: item.subcategory_name,
            item_name: item.item_name,
            unit_id: item.unit_id,
            unit_symbol: item.unit_symbol,
            quantity: String(item.default_quantity !== null ? item.default_quantity : 1),
            duration: String(item.default_duration !== null ? item.default_duration : 1),
            unit_price: String(item.default_unit_price !== null ? item.default_unit_price : item.master_default_price),
          }));

          this.lineItems = this.lineItems.concat(rows);
          this.$toasted.show(`Template "${template.template_name}" berhasil dimuat.`, { type: 'success' });
        })
        .catch((error) => {
          const message = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal memuat template.';
          this.$toasted.show(message, { type: 'error' });
        })
        .finally(() => {
          this.isLoadingTemplateDetail = false;
        });
    },
    submitForReview() {
      axios.post(`/basic-costs/${this.$route.params.id}/submit`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'Basic cost berhasil dikirim untuk review.', { type: 'success' });
          this.loadDocument();
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal mengirim basic cost untuk review.';
        });
    },
    requestRevision() {
      if (!this.approvalActionNote.trim()) {
        this.errorMessage = 'Catatan revisi wajib diisi.';
        return;
      }

      axios.post(`/basic-costs/${this.$route.params.id}/request-revision`, {
        note: this.approvalActionNote,
      }).then((response) => {
        this.approvalActionNote = '';
        this.$toasted.show(response.data.message || 'Basic cost dikembalikan untuk revisi.', { type: 'success' });
        this.loadDocument();
      }).catch((error) => {
        this.errorMessage = error && error.response && error.response.data && error.response.data.message
          ? error.response.data.message
          : 'Gagal meminta revisi basic cost.';
      });
    },
    approveDocument() {
      axios.post(`/basic-costs/${this.$route.params.id}/approve`, {
        note: this.approvalActionNote,
      }).then((response) => {
        this.approvalActionNote = '';
        this.$toasted.show(response.data.message || 'Basic cost berhasil disetujui.', { type: 'success' });
        this.loadDocument();
      }).catch((error) => {
        this.errorMessage = error && error.response && error.response.data && error.response.data.message
          ? error.response.data.message
          : 'Gagal menyetujui basic cost.';
      });
    },
    removeLine(id) {
      this.lineItems = this.lineItems.filter((item) => item.id !== id);
    },
    normalizeLine(line) {
      line.quantity = String(Number(line.quantity || 0));
      line.duration = String(Number(line.duration || 0));
    },
    updateUnitPrice(line, value) {
      line.unit_price = this.parseCurrencyInput(value);
    },
    handleUnitPriceBlur() {
      this.activeUnitPriceRowId = null;
    },
    lineTotal(line) {
      return Number(line.quantity || 0) * Number(line.duration || 0) * Number(line.unit_price || 0);
    },
    formatCurrency(value) {
      return `Rp ${Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    formatCurrencyInput(value) {
      if (value === '' || value === null || typeof value === 'undefined') {
        return '';
      }

      return this.formatCurrency(Number(value || 0));
    },
    displayUnitPrice(line) {
      if (this.activeUnitPriceRowId === line.id) {
        return this.typingUnitPriceValue(line.unit_price);
      }

      return this.formatCurrencyInput(line.unit_price);
    },
    typingUnitPriceValue(value) {
      if (value === '' || value === null || typeof value === 'undefined') {
        return '';
      }

      const amount = Number(value || 0);
      return Number.isNaN(amount) ? '' : String(amount).replace('.', ',');
    },
    parseCurrencyInput(value) {
      const raw = String(value || '').replace(/[^0-9,]/g, '');
      if (!raw) {
        return '';
      }

      const parts = raw.split(',');
      const integerPart = (parts[0] || '0').replace(/^0+(?=\d)/, '') || '0';
      const decimalPart = parts.length > 1 ? parts[1].slice(0, 2) : '';

      if (decimalPart !== '') {
        return `${integerPart}.${decimalPart}`;
      }

      return integerPart;
    },
    formatHistoryAction(action) {
      const labels = {
        created: 'Dokumen Dibuat',
        updated: 'Dokumen Diperbarui',
        submitted: 'Submit for Review',
        revision_requested: 'Request Revision',
        approved: 'Approved',
      };

      return labels[action] || action;
    },
    async exportDetail() {
      if (!this.isEditMode) {
        return;
      }

      try {
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Cost Estimator';
        workbook.company = 'Cost Estimator';
        workbook.created = new Date();
        workbook.modified = new Date();

        this.buildExportSummarySheet(workbook);
        this.buildExportDetailSheet(workbook);
        this.buildExportApprovalSheet(workbook);

        const safeProjectName = (this.form.project_name || 'basic-cost')
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-|-$/g, '')
          .slice(0, 50) || 'basic-cost';

        const buffer = await workbook.xlsx.writeBuffer();
        this.downloadExcelFile(
          buffer,
          `basic-cost-detail-${safeProjectName}-${this.$route.params.id}.xlsx`,
        );
        this.$toasted.show('Export detail basic cost berhasil dibuat.', { type: 'success' });
      } catch (error) {
        this.errorMessage = error && error.message
          ? `Export Excel gagal: ${error.message}`
          : 'Export Excel gagal. Silakan coba lagi.';
        this.$toasted.show('Export Excel gagal. Silakan coba lagi.', { type: 'error' });
      }
    },
    buildExportSummarySheet(workbook) {
      const worksheet = workbook.addWorksheet('Ringkasan', {
        views: [{ state: 'frozen', ySplit: 3 }],
      });

      worksheet.columns = [
        { width: 24 },
        { width: 52 },
      ];

      this.addSheetTitle(
        worksheet,
        'LAPORAN DETAIL BASIC COST',
        `Project: ${this.form.project_name || '-'} | ID Dokumen: ${this.$route.params.id}`,
        2,
      );

      let rowIndex = 4;
      rowIndex = this.addKeyValueSection(worksheet, rowIndex, 'INFORMASI DOKUMEN', [
        ['Basic Cost ID', Number(this.$route.params.id)],
        ['Nama Klien', this.form.client_name || '-'],
        ['Nama Proyek', this.form.project_name || '-'],
        ['Lokasi', this.form.location || '-'],
        ['Tanggal Basic Cost', this.form.basic_cost_date || '-'],
        ['Jenis Pekerjaan', this.selectedWorkTypeNames()],
        ['Catatan', this.form.notes || '-'],
      ]);

      rowIndex += 1;

      rowIndex = this.addKeyValueSection(worksheet, rowIndex, 'RINGKASAN APPROVAL', [
        ['Status Approval', this.statusLabel],
        ['Diajukan Pada', this.documentMeta.submitted_at || '-'],
        ['Diajukan Oleh', this.documentMeta.submitted_by_name || '-'],
        ['Direview Pada', this.documentMeta.reviewed_at || '-'],
        ['Direview Oleh', this.documentMeta.reviewed_by_name || '-'],
        ['Catatan Atasan', this.documentMeta.review_notes || '-'],
      ]);

      rowIndex += 1;

      this.addKeyValueSection(worksheet, rowIndex, 'RINGKASAN NILAI', [
        ['Jumlah Item', this.lineItems.length],
        ['Grand Total', Number(this.grandTotal || 0)],
      ], {
        currencyValueRows: [2],
        emphasizeLastRow: true,
      });

      return worksheet;
    },
    buildExportDetailSheet(workbook) {
      const worksheet = workbook.addWorksheet('Detail Item', {
        views: [{ state: 'frozen', ySplit: 2 }],
      });

      worksheet.columns = [
        { width: 7 },
        { width: 54 },
        { width: 12 },
        { width: 12 },
        { width: 12 },
        { width: 20 },
        { width: 22 },
      ];
      worksheet.pageSetup = { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 };
      worksheet.pageMargins = { left: 0.25, right: 0.25, top: 0.5, bottom: 0.5, header: 0.2, footer: 0.2 };

      this.addSheetTitle(
        worksheet,
        'DETAIL ITEM BASIC COST',
        `Project: ${this.form.project_name || '-'} | Klien: ${this.form.client_name || '-'} | Status: ${this.statusLabel}`,
        7,
      );

      const grouped = {};
      this.lineItems.forEach((item) => {
        const key = item.category_name || '-';
        if (!grouped[key]) {
          grouped[key] = [];
        }
        grouped[key].push(item);
      });

      let runningNumber = 1;
      sortCategoryNames(Object.keys(grouped)).forEach((categoryName) => {
        const categoryRowIndex = worksheet.lastRow.number + 1;
        this.addSectionHeader(worksheet, categoryRowIndex, categoryName, 7);
        const headerRow = worksheet.addRow(['No', 'Item', 'Satuan', 'Quantity', 'Duration', 'Unit Price', 'Total']);
        this.styleTableHeaderRow(headerRow);

        grouped[categoryName].forEach((item) => {
          const row = worksheet.addRow([
            runningNumber,
            item.item_name || '-',
            item.unit_symbol || '-',
            Number(item.quantity || 0),
            Number(item.duration || 0),
            Number(item.unit_price || 0),
            Number(this.lineTotal(item) || 0),
          ]);
          this.styleTableDataRow(row, runningNumber % 2 === 0);
          row.getCell(4).alignment = { horizontal: 'center', vertical: 'middle' };
          row.getCell(5).alignment = { horizontal: 'center', vertical: 'middle' };
          this.applyCurrencyCell(row.getCell(6));
          this.applyCurrencyCell(row.getCell(7));
          runningNumber += 1;
        });

        const subtotalRow = worksheet.addRow([
          `Subtotal ${categoryName}`,
          '',
          '',
          '',
          '',
          '',
          Number(grouped[categoryName].reduce((sum, item) => sum + this.lineTotal(item), 0)),
        ]);
        worksheet.mergeCells(subtotalRow.number, 1, subtotalRow.number, 6);
        this.styleSubtotalRow(subtotalRow, 7);
        this.applyCurrencyCell(subtotalRow.getCell(7));
        this.applyOuterBorder(worksheet, categoryRowIndex, subtotalRow.number, 1, 7);
      });

      worksheet.addRow([]);
      const grandTotalRow = worksheet.addRow(['GRAND TOTAL', '', '', '', '', '', Number(this.grandTotal || 0)]);
      worksheet.mergeCells(grandTotalRow.number, 1, grandTotalRow.number, 6);
      this.styleGrandTotalRow(grandTotalRow, 7);
      this.applyCurrencyCell(grandTotalRow.getCell(7));
      return worksheet;
    },
    buildExportApprovalSheet(workbook) {
      const worksheet = workbook.addWorksheet('Approval', {
        views: [{ state: 'frozen', ySplit: 13 }],
      });

      worksheet.columns = [
        { width: 8 },
        { width: 24 },
        { width: 16 },
        { width: 16 },
        { width: 22 },
        { width: 22 },
        { width: 42 },
      ];

      this.addSheetTitle(
        worksheet,
        'RINGKASAN DAN RIWAYAT APPROVAL',
        `Project: ${this.form.project_name || '-'} | Status Saat Ini: ${this.statusLabel}`,
        7,
      );

      let rowIndex = 4;
      rowIndex = this.addKeyValueSection(worksheet, rowIndex, 'RINGKASAN APPROVAL', [
        ['Status Saat Ini', this.statusLabel],
        ['Diajukan Pada', this.documentMeta.submitted_at || '-'],
        ['Diajukan Oleh', this.documentMeta.submitted_by_name || '-'],
        ['Direview Pada', this.documentMeta.reviewed_at || '-'],
        ['Direview Oleh', this.documentMeta.reviewed_by_name || '-'],
        ['Catatan Atasan', this.documentMeta.review_notes || '-'],
      ], {}, 7);

      rowIndex += 1;
      this.addSectionHeader(worksheet, rowIndex, 'RIWAYAT APPROVAL', 7);
      rowIndex += 1;

      const headerRow = worksheet.addRow(['No', 'Aksi', 'Status Dari', 'Status Ke', 'Oleh', 'Waktu', 'Catatan']);
      this.styleTableHeaderRow(headerRow);

      if (this.approvalHistory.length === 0) {
        const row = worksheet.addRow([1, '-', '-', '-', '-', '-', '-']);
        this.styleTableDataRow(row, false);
      } else {
        this.approvalHistory.forEach((item, index) => {
          const row = worksheet.addRow([
            index + 1,
            this.formatHistoryAction(item.action),
            item.status_from || '-',
            item.status_to || '-',
            item.actor_name || 'Sistem',
            item.created_at || '-',
            item.note || '-',
          ]);
          this.styleTableDataRow(row, index % 2 === 1);
        });
      }

      this.applyOuterBorder(worksheet, headerRow.number, worksheet.lastRow.number, 1, 7);
      return worksheet;
    },
    addSheetTitle(worksheet, title, subtitle, totalColumns) {
      worksheet.mergeCells(1, 1, 1, totalColumns);
      worksheet.mergeCells(2, 1, 2, totalColumns);
      const titleCell = worksheet.getCell(1, 1);
      const subtitleCell = worksheet.getCell(2, 1);
      titleCell.value = title;
      subtitleCell.value = subtitle;
      titleCell.font = { size: 16, bold: true, color: { argb: 'FFFFFFFF' } };
      subtitleCell.font = { size: 11, color: { argb: 'FFDCE6F2' } };
      titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
      subtitleCell.alignment = { horizontal: 'center', vertical: 'middle' };
      titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E78' } };
      subtitleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF2F75B5' } };
      worksheet.getRow(1).height = 24;
      worksheet.getRow(2).height = 20;
    },
    addSectionHeader(worksheet, rowIndex, title, totalColumns = 2) {
      worksheet.mergeCells(rowIndex, 1, rowIndex, totalColumns);
      const cell = worksheet.getCell(rowIndex, 1);
      cell.value = title;
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 11 };
      cell.alignment = { horizontal: 'left', vertical: 'middle' };
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF5B9BD5' } };
      cell.border = this.fullBorder('FF9CC2E5');
      worksheet.getRow(rowIndex).height = 18;
    },
    addKeyValueSection(worksheet, startRow, title, rows, options = {}, mergeColumns = 2) {
      this.addSectionHeader(worksheet, startRow, title, mergeColumns);
      let rowIndex = startRow + 1;
      rows.forEach((entry, entryIndex) => {
        const row = worksheet.getRow(rowIndex);
        row.getCell(1).value = entry[0];
        row.getCell(2).value = entry[1];
        row.getCell(1).font = { bold: true, color: { argb: 'FF1F1F1F' } };
        row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEAF2F8' } };
        row.getCell(2).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: entryIndex % 2 === 0 ? 'FFFFFFFF' : 'FFF8FBFF' } };
        row.getCell(1).border = this.fullBorder('FFD9E2F3');
        row.getCell(2).border = this.fullBorder('FFD9E2F3');
        row.getCell(1).alignment = { vertical: 'middle' };
        row.getCell(2).alignment = { vertical: 'middle', wrapText: true };
        if (options.currencyValueRows && options.currencyValueRows.includes(entryIndex + 1)) {
          this.applyCurrencyCell(row.getCell(2));
        }
        if (options.emphasizeLastRow && entryIndex === rows.length - 1) {
          row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9EAD3' } };
          row.getCell(2).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2F0D9' } };
          row.font = { bold: true };
        }
        rowIndex += 1;
      });
      return rowIndex;
    },
    styleTableHeaderRow(row) {
      row.height = 20;
      row.eachCell((cell) => {
        cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF4472C4' } };
        cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
        cell.border = this.fullBorder('FFB4C6E7');
      });
    },
    styleTableDataRow(row, shaded) {
      row.eachCell((cell) => {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: shaded ? 'FFF7FBFF' : 'FFFFFFFF' },
        };
        cell.border = this.fullBorder('FFE3EAF3');
        cell.alignment = { vertical: 'middle', wrapText: true };
      });
      row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
      row.getCell(row.cellCount - 1).alignment = { horizontal: 'right', vertical: 'middle' };
      row.getCell(row.cellCount).alignment = { horizontal: 'right', vertical: 'middle' };
    },
    styleSubtotalRow(row, totalColumns) {
      row.eachCell((cell, colNumber) => {
        cell.border = this.fullBorder('FFBDD7EE');
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFDDEBF7' } };
        cell.font = { bold: true, color: { argb: 'FF1F1F1F' } };
        cell.alignment = { vertical: 'middle' };
        if (colNumber === totalColumns) {
          cell.alignment = { horizontal: 'right', vertical: 'middle' };
        }
      });
    },
    styleGrandTotalRow(row, totalColumns) {
      row.eachCell((cell, colNumber) => {
        cell.border = this.fullBorder('FFA9D18E');
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2F0D9' } };
        cell.font = { bold: true, size: 11, color: { argb: 'FF274E13' } };
        cell.alignment = { vertical: 'middle' };
        if (colNumber === totalColumns) {
          cell.alignment = { horizontal: 'right', vertical: 'middle' };
        }
      });
    },
    applyCurrencyCell(cell) {
      cell.numFmt = '"Rp" #,##0.00';
      cell.alignment = { horizontal: 'right', vertical: 'middle' };
    },
    applyOuterBorder(worksheet, startRow, endRow, startCol, endCol) {
      for (let rowIndex = startRow; rowIndex <= endRow; rowIndex += 1) {
        for (let colIndex = startCol; colIndex <= endCol; colIndex += 1) {
          const cell = worksheet.getCell(rowIndex, colIndex);
          const currentBorder = cell.border || {};
          cell.border = {
            top: rowIndex === startRow ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.top,
            left: colIndex === startCol ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.left,
            bottom: rowIndex === endRow ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.bottom,
            right: colIndex === endCol ? { style: 'medium', color: { argb: 'FF9FBAD0' } } : currentBorder.right,
          };
        }
      }
    },
    fullBorder(color = 'FFD9E2F3') {
      return {
        top: { style: 'thin', color: { argb: color } },
        left: { style: 'thin', color: { argb: color } },
        bottom: { style: 'thin', color: { argb: color } },
        right: { style: 'thin', color: { argb: color } },
      };
    },
    downloadExcelFile(buffer, filename) {
      const blob = new Blob(
        [buffer],
        { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
      );
      const link = document.createElement('a');
      link.href = window.URL.createObjectURL(blob);
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => {
        window.URL.revokeObjectURL(link.href);
      }, 1000);
    },
    selectedWorkTypeNames() {
      const names = this.form.work_type_category_ids
        .map((selectedId) => this.workTypeOptions.find((option) => option.value === String(selectedId)))
        .filter(Boolean)
        .map((option) => option.text);

      return names.length > 0 ? names.join(', ') : '-';
    },
    submitForm() {
      this.errorMessage = '';

      if (!this.form.client_name || !this.form.project_name || !this.form.basic_cost_date) {
        this.errorMessage = 'Header proyek belum lengkap.';
        return;
      }

      if (this.lineItems.length === 0) {
        this.errorMessage = 'Minimal harus ada satu item basic cost.';
        return;
      }

      this.isSubmitting = true;
      const payload = {
        client_name: this.form.client_name,
        project_name: this.form.project_name,
        location: this.form.location,
        basic_cost_date: this.form.basic_cost_date,
        work_type_category_ids: this.form.work_type_category_ids.filter(Boolean),
        notes: this.form.notes,
        items: this.lineItems.map((item) => ({
          cost_item_id: item.cost_item_id,
          quantity: Number(item.quantity || 0),
          duration: Number(item.duration || 0),
          unit_price: Number(item.unit_price || 0),
          source_type: item.source_type || null,
          source_key: item.source_key || null,
        })),
      };

      const request = this.isEditMode
        ? axios.put(`/basic-costs/${this.$route.params.id}`, payload)
        : axios.post('/basic-costs', payload);

      request.then((response) => {
        this.$toasted.show(response.data.message || 'Basic cost berhasil disimpan.', { type: 'success' });
        this.$router.push('/app/basic-costs');
      }).catch((error) => {
        if (error && error.response && error.response.data) {
          const responseData = error.response.data;
          if (responseData.errors) {
            const firstErrorKey = Object.keys(responseData.errors)[0];
            this.errorMessage = responseData.errors[firstErrorKey];
          } else {
            this.errorMessage = responseData.message || 'Gagal menyimpan basic cost.';
          }
        } else {
          this.errorMessage = 'Gagal menyimpan basic cost.';
        }
      }).finally(() => {
        this.isSubmitting = false;
      });
    },
    generateLineId() {
      const current = this.nextLineId;
      this.nextLineId += 1;
      return current;
    },
  },
  created() {
    this.loadReferences().then(() => {
      this.loadTemplates();
      this.loadDocument();
    });
  },
  watch: {
    'form.work_type_category_ids'() {
      this.loadTemplates();
    },
  },
};
</script>

<style src="./BasicCost.scss" lang="scss" scoped />
