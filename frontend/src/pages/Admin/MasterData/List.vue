<template>
  <div class="master-data-page">
    <b-breadcrumb>
      <b-breadcrumb-item>MASTER DATA</b-breadcrumb-item>
      <b-breadcrumb-item active>{{ config.breadcrumb }}</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">{{ config.title }}</h2>
        <p class="page-subtitle">Kelola data {{ config.singular.toLowerCase() }} untuk kebutuhan costing perusahaan.</p>
      </div>
      <b-button variant="primary" @click="handlePrimaryAction">
        Tambah {{ config.singular }}
      </b-button>
    </div>

    <Widget v-if="usesInlineForm" :title="formWidgetTitle" customHeader class="mb-xlg">
      <b-alert variant="danger" :show="!!formErrorMessage">{{ formErrorMessage }}</b-alert>
      <b-alert variant="success" :show="!!successMessage">{{ successMessage }}</b-alert>

      <b-form @submit.prevent="submitInlineForm">
        <b-row>
          <template v-if="entityType === 'categories'">
            <b-col md="8">
              <b-form-group label="Nama Kategori">
                <b-form-input v-model="inlineForm.name" required placeholder="Contoh: Topography Survey" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Status">
                <b-form-select v-model="inlineForm.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'subcategories'">
            <b-col md="4">
              <b-form-group label="Kategori">
                <b-form-select v-model="inlineForm.category_id" :options="inlineCategoryOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Satuan">
                <b-form-select v-model="inlineForm.unit_id" :options="unitOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Nama Subkategori">
                <b-form-input v-model="inlineForm.name" required placeholder="Contoh: Field Work" />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Durasi Default">
                <b-form-input v-model="inlineForm.default_duration" type="number" step="0.01" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Harga Dasar">
                <b-form-input v-model="inlineForm.default_price" type="number" step="0.01" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Deskripsi">
                <b-form-textarea v-model="inlineForm.description" rows="3" placeholder="Catatan master subkategori" />
              </b-form-group>
            </b-col>
            <b-col md="2">
              <b-form-group label="Status">
                <b-form-select v-model="inlineForm.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'units'">
            <b-col md="5">
              <b-form-group label="Nama Satuan">
                <b-form-input v-model="inlineForm.name" required placeholder="Contoh: Day" />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Simbol">
                <b-form-input v-model="inlineForm.symbol" required placeholder="Contoh: day" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Status">
                <b-form-select v-model="inlineForm.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'signatories'">
            <b-col md="5">
              <b-form-group label="Nama Penandatangan">
                <b-form-input v-model="inlineForm.name" required placeholder="Contoh: Rusli Dain" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Jabatan">
                <b-form-input v-model="inlineForm.position_title" required placeholder="Contoh: Direktur" />
              </b-form-group>
            </b-col>
            <b-col md="1">
              <b-form-group label="Urutan">
                <b-form-input v-model="inlineForm.sort_order" type="number" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="2">
              <b-form-group label="Status">
                <b-form-select v-model="inlineForm.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
            <b-col md="12">
              <b-form-group label="File Tanda Tangan (PNG)">
                <b-form-file
                  accept=".png,image/png"
                  placeholder="Pilih file PNG"
                  drop-placeholder="Drop file PNG di sini"
                  @input="onInlineSignatureFileSelected"
                />
                <small class="text-muted d-block mt-2">Gunakan PNG dengan background transparan agar hasil proposal lebih rapi.</small>
              </b-form-group>
            </b-col>
            <b-col v-if="inlineSignaturePreviewUrl" md="12">
              <div class="signature-preview-box">
                <img :src="inlineSignaturePreviewUrl" alt="Preview tanda tangan" class="signature-preview-image">
                <div class="signature-preview-actions">
                  <b-button size="sm" variant="inverse" @click="clearInlineSignatureImage">Hapus Gambar</b-button>
                </div>
              </div>
            </b-col>
          </template>
        </b-row>

        <div class="form-actions">
          <b-button type="submit" variant="primary" :disabled="isSubmittingInline">
            {{ isSubmittingInline ? 'Menyimpan...' : (editingId ? `Update ${config.singular}` : `Simpan ${config.singular}`) }}
          </b-button>
          <b-button
            v-if="editingId"
            type="button"
            variant="inverse"
            class="ms-2"
            @click="resetInlineForm"
          >
            Batal Edit
          </b-button>
        </div>
      </b-form>
    </Widget>

    <Widget v-if="entityType === 'subcategories'" title="<h5>Template <span class='fw-semi-bold'>Excel</span></h5>" customHeader class="mb-xlg">
      <b-alert variant="info" :show="true" class="mb-md">
        Download template Excel berdasarkan data subkategori yang sudah ada, lalu upload kembali file untuk import massal.
      </b-alert>
      <b-alert variant="danger" :show="!!importErrorMessage" class="mb-md">{{ importErrorMessage }}</b-alert>
      <b-alert variant="success" :show="!!importSuccessMessage" class="mb-md">{{ importSuccessMessage }}</b-alert>

      <b-row>
        <b-col md="4" class="d-flex align-items-end mb-md">
          <b-button variant="success" class="w-100" @click="downloadSubcategoryTemplate">
            Download Template Excel
          </b-button>
        </b-col>
        <b-col md="5">
          <b-form-group label="Upload File Excel">
            <b-form-file
              v-model="importFile"
              accept=".xlsx"
              placeholder="Pilih file Excel"
              drop-placeholder="Drop file Excel di sini"
            />
          </b-form-group>
        </b-col>
        <b-col md="3" class="d-flex align-items-end mb-md">
          <b-button variant="primary" class="w-100" :disabled="!importFile || isImporting" @click="importSubcategoryTemplate">
            {{ isImporting ? 'Mengimpor...' : 'Upload & Import' }}
          </b-button>
        </b-col>
      </b-row>
    </Widget>

    <Widget :title="widgetTitle" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <div v-if="hasFilters" class="filter-bar">
        <b-row>
          <b-col v-if="showKeywordFilter" :md="showCategoryFilter && showStatusFilter ? 5 : 8">
            <b-form-group label="Cari Item">
              <b-form-input v-model="filters.q" :placeholder="searchPlaceholder" />
            </b-form-group>
          </b-col>
          <b-col v-if="showCategoryFilter" md="4">
            <b-form-group label="Kategori">
              <b-form-select v-model="filters.category_id" :options="categoryOptions" />
            </b-form-group>
          </b-col>
          <b-col v-if="showStatusFilter" :md="showCategoryFilter || showKeywordFilter ? 3 : 4">
            <b-form-group label="Status">
              <b-form-select v-model="filters.is_active" :options="statusFilterOptions" />
            </b-form-group>
          </b-col>
        </b-row>
        <div class="form-actions compact">
          <b-button variant="primary" @click="loadRecords">Filter</b-button>
          <b-button variant="inverse" class="ms-2" @click="resetFilters">Reset</b-button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover master-table">
          <colgroup>
            <col v-for="column in config.columns" :key="`col-${column.key}`" :class="column.className || ''">
            <col class="col-actions">
          </colgroup>
          <thead>
            <tr>
              <th v-for="column in config.columns" :key="column.key" :class="column.className || ''">{{ column.label }}</th>
              <th class="text-end action-column">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td :colspan="config.columns.length + 1" class="text-center text-muted py-4">Memuat data...</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td :colspan="config.columns.length + 1" class="text-center text-muted py-4">Belum ada data tersimpan.</td>
            </tr>
            <tr v-for="record in records" :key="record.id">
              <td v-for="column in config.columns" :key="`${record.id}-${column.key}`" :class="column.className || ''">
                <span v-if="column.type === 'status'" class="status-badge" :class="record[column.key] ? 'active' : 'inactive'">
                  {{ record[column.key] ? 'Active' : 'Inactive' }}
                </span>
                <span v-else-if="column.type === 'currency'">
                  {{ formatCurrency(record[column.key]) }}
                </span>
                <span v-else-if="column.type === 'image'">
                  <img v-if="record[column.key]" :src="record[column.key]" alt="Preview" class="table-image-preview">
                  <span v-else>-</span>
                </span>
                <span v-else>
                  {{ record[column.key] || '-' }}
                </span>
              </td>
              <td>
                <div class="table-actions">
                  <b-button size="sm" variant="default" @click="goToEdit(record.id)">Edit</b-button>
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
import ExcelJS from 'exceljs';
import Widget from '@/components/Widget/Widget';
import { getMasterDataConfig } from './config';

export default {
  name: 'MasterDataList',
  components: { Widget },
  data() {
    return {
      records: [],
      isLoading: false,
      errorMessage: '',
      formErrorMessage: '',
      successMessage: '',
      editingId: null,
      isSubmittingInline: false,
      isImporting: false,
      categoryOptions: [{ value: '', text: 'Semua Kategori' }],
      inlineCategoryOptions: [{ value: '', text: 'Pilih Kategori' }],
      unitOptions: [{ value: '', text: 'Pilih Satuan' }],
      importFile: null,
      importErrorMessage: '',
      importSuccessMessage: '',
      subcategoryOptions: [{ value: '', text: 'Semua Subkategori' }],
      allSubcategoryOptions: [],
      statusOptions: [
        { value: '1', text: 'Active' },
        { value: '0', text: 'Inactive' },
      ],
      statusFilterOptions: [
        { value: '', text: 'Semua Status' },
        { value: '1', text: 'Active' },
        { value: '0', text: 'Inactive' },
      ],
      filters: {
        q: '',
        category_id: '',
        subcategory_id: '',
        is_active: '',
      },
      inlineForm: {
        name: '',
        category_id: '',
        unit_id: '',
        default_duration: '0',
        default_price: '0',
        description: '',
        symbol: '',
        position_title: '',
        signature_image_base64: '',
        signature_image_url: '',
        remove_signature_image: false,
        sort_order: '0',
        is_active: '1',
      },
    };
  },
  computed: {
    entityType() {
      return this.$route.meta.entityType;
    },
    config() {
      return getMasterDataConfig(this.entityType);
    },
    hasFilters() {
      return !!this.config.filters;
    },
    showKeywordFilter() {
      return !!(this.config.filters && this.config.filters.keyword);
    },
    showCategoryFilter() {
      return !!(this.config.filters && this.config.filters.category);
    },
    showStatusFilter() {
      return !!(this.config.filters && this.config.filters.status);
    },
    searchPlaceholder() {
      const placeholders = {
        categories: 'Cari nama kategori',
        subcategories: 'Cari kategori atau subkategori',
        units: 'Cari nama atau simbol satuan',
        costItems: 'Cari nama atau deskripsi item',
        signatories: 'Cari nama atau jabatan penandatangan',
      };

      return placeholders[this.entityType] || 'Cari data';
    },
    widgetTitle() {
      return `<h5>Daftar <span class='fw-semi-bold'>${this.config.singular}</span></h5>`;
    },
    formWidgetTitle() {
      return `<h5>${this.editingId ? 'Edit' : 'Input'} <span class='fw-semi-bold'>${this.config.singular}</span></h5>`;
    },
    usesInlineForm() {
      return ['categories', 'subcategories', 'units', 'signatories'].includes(this.entityType);
    },
    inlineSignaturePreviewUrl() {
      return this.inlineForm.signature_image_base64 || this.inlineForm.signature_image_url || '';
    },
  },
  methods: {
    handlePrimaryAction() {
      if (this.usesInlineForm) {
        this.resetInlineForm();
        this.scrollToTop();
        return;
      }

      this.$router.push(this.config.createRoute);
    },
    loadRecords() {
      this.isLoading = true;
      this.errorMessage = '';

      const params = {};
      if (this.hasFilters) {
        if (this.filters.q) {
          params.q = this.filters.q;
        }
        if (this.showCategoryFilter && this.filters.category_id) {
          params.category_id = this.filters.category_id;
        }
        if (this.filters.subcategory_id) {
          params.subcategory_id = this.filters.subcategory_id;
        }
        if (this.showStatusFilter && this.filters.is_active !== '') {
          params.is_active = this.filters.is_active;
        }
      }

      axios.get(this.config.endpoint, { params })
        .then((response) => {
          this.records = response.data.data || [];
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, `Gagal memuat data ${this.config.singular.toLowerCase()}.`);
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    loadReferences() {
      const requests = [];

      if (this.showCategoryFilter || this.entityType === 'subcategories') {
        requests.push(
          axios.get('/categories')
            .then((response) => {
              const options = (response.data.data || []).map((item) => ({
                value: String(item.id),
                text: item.name,
              }));
              this.categoryOptions = [{ value: '', text: 'Semua Kategori' }].concat(options);
              this.inlineCategoryOptions = [{ value: '', text: 'Pilih Kategori' }].concat(options);
            }),
        );
      }

      if (this.entityType === 'subcategories') {
        requests.push(
          axios.get('/units')
            .then((response) => {
              const options = (response.data.data || []).map((item) => ({
                value: String(item.id),
                text: `${item.name} (${item.symbol})`,
              }));
              this.unitOptions = [{ value: '', text: 'Pilih Satuan' }].concat(options);
            }),
        );
      }

      if (this.entityType === 'costItems') {
        requests.push(
          axios.get('/subcategories')
            .then((response) => {
              const options = (response.data.data || []).map((item) => ({
                value: String(item.id),
                text: `${item.category_name} - ${item.name}`,
                categoryId: String(item.category_id),
              }));
              this.allSubcategoryOptions = options;
              this.updateSubcategoryOptions();
            }),
        );
      }

      return Promise.all(requests);
    },
    async downloadSubcategoryTemplate() {
      try {
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Cost Estimator';
        workbook.created = new Date();
        workbook.modified = new Date();

        const worksheet = workbook.addWorksheet('Subkategori');
        worksheet.columns = [
          { header: 'ID', key: 'id', width: 10 },
          { header: 'Nama Kategori', key: 'category_name', width: 28 },
          { header: 'Nama Subkategori', key: 'nama_subkategori', width: 30 },
          { header: 'Nama Satuan', key: 'nama_satuan', width: 20 },
          { header: 'Simbol Satuan', key: 'simbol_satuan', width: 18 },
          { header: 'Durasi Default', key: 'durasi_default', width: 18 },
          { header: 'Harga Dasar', key: 'harga_dasar', width: 18 },
          { header: 'Deskripsi', key: 'deskripsi', width: 42 },
          { header: 'Status', key: 'status', width: 14 },
        ];

        const headerRow = worksheet.getRow(1);
        headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } };
        headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF2563EB' } };
        headerRow.alignment = { horizontal: 'center', vertical: 'middle' };

        this.records.forEach((record) => {
          worksheet.addRow({
            id: record.id,
            category_name: record.category_name || '',
            nama_subkategori: record.name || '',
            nama_satuan: record.unit_name || '',
            simbol_satuan: record.unit_symbol || '',
            durasi_default: Number(record.default_duration || 0),
            harga_dasar: Number(record.default_price || 0),
            deskripsi: record.description || '',
            status: record.is_active ? 'Active' : 'Inactive',
          });
        });

        worksheet.getColumn('harga_dasar').numFmt = '#,##0.00';

        const buffer = await workbook.xlsx.writeBuffer();
        this.downloadFile(
          buffer,
          `template-subkategori-${new Date().toISOString().slice(0, 10)}.xlsx`,
        );
      } catch (error) {
        this.importErrorMessage = error && error.message
          ? `Gagal membuat template Excel: ${error.message}`
          : 'Gagal membuat template Excel.';
      }
    },
    async importSubcategoryTemplate() {
      if (!this.importFile) {
        this.importErrorMessage = 'Pilih file Excel terlebih dahulu.';
        return;
      }

      this.isImporting = true;
      this.importErrorMessage = '';
      this.importSuccessMessage = '';

      try {
        const workbook = new ExcelJS.Workbook();
        await workbook.xlsx.load(await this.importFile.arrayBuffer());
        const worksheet = workbook.worksheets[0];

        if (!worksheet) {
          throw new Error('Worksheet tidak ditemukan pada file Excel.');
        }

        const headers = worksheet.getRow(1).values
          .slice(1)
          .map((value) => this.normalizeImportHeader(value));

        const rows = [];
        worksheet.eachRow((row, rowNumber) => {
          if (rowNumber === 1) {
            return;
          }

          const entry = {};
          row.values.slice(1).forEach((value, index) => {
            entry[headers[index]] = value;
          });

          if (String(entry.nama_subkategori || '').trim() !== '') {
            rows.push(entry);
          }
        });

        if (rows.length === 0) {
          throw new Error('Tidak ada data subkategori yang bisa diimport.');
        }

        const response = await axios.post('/subcategories/import', { rows });
        const summary = response.data.summary || {};
        this.importSuccessMessage = `Import selesai. Dibuat ${summary.created || 0}, diperbarui ${summary.updated || 0}, dilewati ${summary.skipped || 0}.`;
        this.$toasted.show(this.importSuccessMessage, { type: 'success' });
        this.importFile = null;
        await Promise.all([this.loadReferences(), this.loadRecords()]);
      } catch (error) {
        const responseData = error && error.response ? error.response.data : null;
        if (responseData && responseData.summary && Array.isArray(responseData.summary.errors)) {
          const details = responseData.summary.errors
            .map((item) => `Baris ${item.row}: ${item.message}`)
            .join(' | ');
          this.importErrorMessage = `${responseData.message || 'Import gagal.'} ${details}`;
        } else {
          this.importErrorMessage = this.getErrorMessage(error, 'Import subkategori gagal.');
        }
      } finally {
        this.isImporting = false;
      }
    },
    updateSubcategoryOptions() {
      const filtered = (this.allSubcategoryOptions || []).filter((option) => {
        if (!this.filters.category_id) {
          return true;
        }

        return option.categoryId === this.filters.category_id;
      }).map(({ value, text }) => ({ value, text }));

      this.subcategoryOptions = [{ value: '', text: 'Semua Subkategori' }].concat(filtered);

      if (this.filters.subcategory_id && !filtered.find((option) => option.value === this.filters.subcategory_id)) {
        this.filters.subcategory_id = '';
      }
    },
    resetFilters() {
      this.filters = {
        q: '',
        category_id: '',
        subcategory_id: '',
        is_active: '',
      };
      this.updateSubcategoryOptions();
      this.loadRecords();
    },
    goToEdit(id) {
      if (this.usesInlineForm) {
        this.loadRecordIntoInlineForm(id);
        return;
      }

      this.$router.push(`${this.config.routeBase}/${id}/edit`);
    },
    loadRecordIntoInlineForm(id) {
      this.formErrorMessage = '';
      this.successMessage = '';

      axios.get(`${this.config.endpoint}/${id}`)
        .then((response) => {
          const data = response.data.data;
          this.editingId = id;
          this.inlineForm.name = data.name || '';
          this.inlineForm.is_active = data.is_active ? '1' : '0';
          this.inlineForm.category_id = data.category_id ? String(data.category_id) : '';
          this.inlineForm.unit_id = data.unit_id ? String(data.unit_id) : '';
          this.inlineForm.default_duration = data.default_duration != null ? String(data.default_duration) : '0';
          this.inlineForm.default_price = data.default_price != null ? String(data.default_price) : '0';
          this.inlineForm.description = data.description || '';
          this.inlineForm.symbol = data.symbol || '';
          this.inlineForm.position_title = data.position_title || '';
          this.inlineForm.signature_image_base64 = '';
          this.inlineForm.signature_image_url = data.signature_image_url || '';
          this.inlineForm.remove_signature_image = false;
          this.inlineForm.sort_order = data.sort_order != null ? String(data.sort_order) : '0';
          this.scrollToTop();
        })
        .catch((error) => {
          this.formErrorMessage = this.getErrorMessage(error, `Gagal memuat data ${this.config.singular.toLowerCase()}.`);
        });
    },
    submitInlineForm() {
      this.isSubmittingInline = true;
      this.formErrorMessage = '';
      this.successMessage = '';

      const payload = {
        name: this.inlineForm.name,
        is_active: this.inlineForm.is_active,
      };

      if (this.entityType === 'subcategories') {
        payload.category_id = this.inlineForm.category_id;
        payload.unit_id = this.inlineForm.unit_id;
        payload.default_duration = this.inlineForm.default_duration;
        payload.default_price = this.inlineForm.default_price;
        payload.description = this.inlineForm.description;
      }

      if (this.entityType === 'units') {
        payload.symbol = this.inlineForm.symbol;
      }

      if (this.entityType === 'signatories') {
        payload.position_title = this.inlineForm.position_title;
        payload.signature_image_base64 = this.inlineForm.signature_image_base64;
        payload.remove_signature_image = this.inlineForm.remove_signature_image ? '1' : '0';
        payload.sort_order = this.inlineForm.sort_order;
      }

      const request = this.editingId
        ? axios.put(`${this.config.endpoint}/${this.editingId}`, payload)
        : axios.post(this.config.endpoint, payload);

      request.then((response) => {
        this.successMessage = response.data.message || 'Data berhasil disimpan.';
        this.$toasted.show(this.successMessage, { type: 'success' });
        this.resetInlineForm();
        this.loadRecords();
      }).catch((error) => {
        if (error && error.response && error.response.data && error.response.data.errors) {
          const firstErrorKey = Object.keys(error.response.data.errors)[0];
          this.formErrorMessage = error.response.data.errors[firstErrorKey];
        } else {
          this.formErrorMessage = this.getErrorMessage(error, `Gagal menyimpan ${this.config.singular.toLowerCase()}.`);
        }
      }).finally(() => {
        this.isSubmittingInline = false;
      });
    },
    resetInlineForm() {
      this.editingId = null;
      this.formErrorMessage = '';
      this.successMessage = '';
      this.inlineForm = {
        name: '',
        category_id: '',
        unit_id: '',
        default_duration: '0',
        default_price: '0',
        description: '',
        symbol: '',
        position_title: '',
        signature_image_base64: '',
        signature_image_url: '',
        remove_signature_image: false,
        sort_order: '0',
        is_active: '1',
      };
    },
    deleteRecord(record) {
      if (!window.confirm(`Hapus ${this.config.singular.toLowerCase()} "${record.name || record.item_name}"?`)) {
        return;
      }

      axios.delete(`${this.config.endpoint}/${record.id}`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'Data berhasil dihapus.', { type: 'success' });
          if (this.editingId === record.id) {
            this.resetInlineForm();
          }
          this.loadRecords();
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, `Gagal menghapus ${this.config.singular.toLowerCase()}.`);
        });
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
    formatCurrency(value) {
      const amount = Number(value || 0);
      return `Rp ${amount.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    onInlineSignatureFileSelected(file) {
      if (!file) {
        return;
      }

      if (file.type !== 'image/png') {
        this.formErrorMessage = 'File tanda tangan harus berupa PNG.';
        return;
      }

      this.formErrorMessage = '';
      const reader = new FileReader();
      reader.onload = () => {
        this.inlineForm.signature_image_base64 = typeof reader.result === 'string' ? reader.result : '';
        this.inlineForm.signature_image_url = '';
        this.inlineForm.remove_signature_image = false;
      };
      reader.onerror = () => {
        this.formErrorMessage = 'Gagal membaca file tanda tangan.';
      };
      reader.readAsDataURL(file);
    },
    clearInlineSignatureImage() {
      this.inlineForm.signature_image_base64 = '';
      this.inlineForm.signature_image_url = '';
      this.inlineForm.remove_signature_image = true;
    },
    normalizeImportHeader(value) {
      return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[\s/()-]+/g, '_');
    },
    downloadFile(buffer, filename) {
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
  },
  watch: {
    'filters.category_id'() {
      if (this.entityType === 'costItems') {
        this.updateSubcategoryOptions();
      }
    },
    '$route.meta.entityType'() {
      this.records = [];
      this.errorMessage = '';
      this.resetInlineForm();
      this.resetFilters();
      this.loadReferences();
      this.loadRecords();
    },
  },
  created() {
    this.loadReferences();
    this.loadRecords();
  },
};
</script>

<style src="./MasterData.scss" lang="scss" scoped />
