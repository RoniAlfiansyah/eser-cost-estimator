<template>
  <div class="template-page">
    <b-breadcrumb>
      <b-breadcrumb-item>TEMPLATE</b-breadcrumb-item>
      <b-breadcrumb-item to="/app/admin/templates">Daftar Template</b-breadcrumb-item>
      <b-breadcrumb-item active>{{ isEditMode ? 'Edit Template' : 'Buat Template' }}</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">{{ isEditMode ? 'Edit' : 'Buat' }} <span class="fw-semi-bold">Template</span></h2>
        <p class="page-subtitle">Susun kumpulan item master yang sering dipakai agar pengisian basic cost menjadi lebih cepat.</p>
      </div>
      <b-button variant="default" @click="$router.push('/app/admin/templates')">
        Kembali
      </b-button>
    </div>

    <Widget title="<h5>Header <span class='fw-semi-bold'>Template</span></h5>" customHeader class="mb-xlg">
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <b-form>
        <b-row>
          <b-col md="4">
            <b-form-group label="Jenis Pekerjaan">
              <b-form-select v-model="form.work_type_id" :options="workTypeOptions" required />
            </b-form-group>
          </b-col>
          <b-col md="5">
            <b-form-group label="Nama Template">
              <b-form-input v-model="form.template_name" required placeholder="Contoh: Topography Standard" />
            </b-form-group>
          </b-col>
          <b-col md="3">
            <b-form-group label="Status">
              <b-form-select v-model="form.is_active" :options="statusOptions" />
            </b-form-group>
          </b-col>
          <b-col md="12">
            <b-form-group label="Deskripsi">
              <b-form-textarea v-model="form.description" rows="2" placeholder="Catatan singkat tentang template ini" />
            </b-form-group>
          </b-col>
        </b-row>
      </b-form>
    </Widget>

    <Widget title="<h5>Tambahkan <span class='fw-semi-bold'>Item Master</span></h5>" customHeader class="mb-xlg">
      <b-row>
        <b-col md="4">
          <b-form-group label="Filter Kategori">
            <b-form-select v-model="picker.category_id" :options="categoryFilterOptions" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Pilih Item Master">
            <b-form-select v-model="picker.cost_item_id" :options="masterItemOptions" />
          </b-form-group>
        </b-col>
        <b-col md="2" class="d-flex align-items-end">
          <b-button variant="primary" class="w-100" @click="addMasterItem">
            Tambah Item
          </b-button>
        </b-col>
      </b-row>
    </Widget>

    <Widget title="<h5>Daftar <span class='fw-semi-bold'>Item Template</span></h5>" customHeader>
      <div class="table-responsive">
        <table class="table table-hover template-table">
          <thead>
            <tr>
              <th>Kategori</th>
              <th>Subkategori</th>
              <th>Item</th>
              <th>Satuan</th>
              <th>Default Qty</th>
              <th>Default Duration</th>
              <th>Default Unit Price</th>
              <th>Sort</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="items.length === 0">
              <td colspan="9" class="text-center py-4">Belum ada item template. Tambahkan item master di atas.</td>
            </tr>
            <tr v-for="item in items" :key="item.id">
              <td>{{ item.category_name }}</td>
              <td>{{ item.subcategory_name || '-' }}</td>
              <td>{{ item.item_name }}</td>
              <td>{{ item.unit_symbol || '-' }}</td>
              <td>
                <b-form-input v-model="item.default_quantity" type="number" step="1" min="0" @input="normalizeItem(item)" />
              </td>
              <td>
                <b-form-input v-model="item.default_duration" type="number" step="1" min="0" @input="normalizeItem(item)" />
              </td>
              <td>
                <b-form-input
                  :value="displayDefaultUnitPrice(item)"
                  type="text"
                  inputmode="decimal"
                  @focus="activeUnitPriceItemId = item.id"
                  @blur="handleDefaultUnitPriceBlur(item)"
                  @input="updateDefaultUnitPrice(item, $event)"
                />
              </td>
              <td>
                <b-form-input v-model="item.sort_order" type="number" step="1" min="1" @input="normalizeSort(item)" />
              </td>
              <td>
                <div class="table-actions">
                  <b-button size="sm" variant="danger" @click="removeItem(item.id)">Hapus</b-button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="form-actions">
        <b-button variant="primary" :disabled="isSubmitting" @click="submitForm">
          {{ isSubmitting ? 'Menyimpan...' : (isEditMode ? 'Update Template' : 'Simpan Template') }}
        </b-button>
        <b-button variant="inverse" @click="$router.push('/app/admin/templates')">
          Batal
        </b-button>
      </div>
    </Widget>
  </div>
</template>

<script>
import axios from 'axios';
import Widget from '@/components/Widget/Widget';

export default {
  name: 'TemplateFormPage',
  components: { Widget },
  data() {
    return {
      form: {
        work_type_id: '',
        template_name: '',
        description: '',
        is_active: '1',
      },
      picker: {
        category_id: '',
        cost_item_id: '',
      },
      items: [],
      activeUnitPriceItemId: null,
      masterItems: [],
      workTypeOptions: [{ value: '', text: 'Pilih Jenis Pekerjaan' }],
      categoryFilterOptions: [{ value: '', text: 'Semua Kategori' }],
      statusOptions: [
        { value: '1', text: 'Active' },
        { value: '0', text: 'Inactive' },
      ],
      errorMessage: '',
      isSubmitting: false,
      nextItemId: 1,
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
  },
  methods: {
    loadReferences() {
      return Promise.all([
        axios.get('/categories').then((response) => {
          const options = (response.data.data || []).map((item) => ({
            value: String(item.id),
            text: item.name,
          }));
          this.workTypeOptions = [{ value: '', text: 'Pilih Jenis Pekerjaan' }].concat(options);
          this.categoryFilterOptions = [{ value: '', text: 'Semua Kategori' }].concat(options);
        }),
        axios.get('/cost-items').then((response) => {
          this.masterItems = response.data.data || [];
        }),
      ]);
    },
    loadTemplate() {
      if (!this.isEditMode) {
        return;
      }

      axios.get(`/templates/${this.$route.params.id}`)
        .then((response) => {
          const data = response.data.data;
          this.form.work_type_id = String(data.work_type_id);
          this.form.template_name = data.template_name;
          this.form.description = data.description || '';
          this.form.is_active = data.is_active ? '1' : '0';
          this.items = (data.items || []).map((item) => ({
            id: this.generateItemId(),
            cost_item_id: item.cost_item_id,
            category_id: item.category_id,
            category_name: item.category_name,
            subcategory_id: item.subcategory_id,
            subcategory_name: item.subcategory_name,
            item_name: item.item_name,
            unit_symbol: item.unit_symbol,
            default_quantity: item.default_quantity !== null ? String(item.default_quantity) : '',
            default_duration: item.default_duration !== null ? String(item.default_duration) : '',
            default_unit_price: item.default_unit_price !== null ? String(item.default_unit_price) : '',
            sort_order: String(item.sort_order),
          }));
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, 'Gagal memuat template.');
        });
    },
    addMasterItem() {
      const selected = this.masterItems.find((item) => String(item.id) === this.picker.cost_item_id);
      if (!selected) {
        this.$toasted.show('Pilih item master terlebih dahulu.', { type: 'error' });
        return;
      }

      if (this.items.find((item) => item.cost_item_id === selected.id)) {
        this.$toasted.show('Item master ini sudah ada di template.', { type: 'error' });
        return;
      }

      this.items.push({
        id: this.generateItemId(),
        cost_item_id: selected.id,
        category_id: selected.category_id,
        category_name: selected.category_name,
        subcategory_id: selected.subcategory_id,
        subcategory_name: selected.subcategory_name,
        item_name: selected.item_name,
        unit_symbol: selected.unit_symbol,
        default_quantity: '1',
        default_duration: selected.default_duration ? String(selected.default_duration) : '',
        default_unit_price: selected.default_price ? String(selected.default_price) : '',
        sort_order: String(this.items.length + 1),
      });

      this.picker.cost_item_id = '';
    },
    removeItem(id) {
      this.items = this.items.filter((item) => item.id !== id);
      this.resequenceItems();
    },
    normalizeItem(item) {
      item.default_quantity = this.normalizeNumericString(item.default_quantity);
      item.default_duration = this.normalizeNumericString(item.default_duration);
    },
    updateDefaultUnitPrice(item, value) {
      item.default_unit_price = this.parseCurrencyInput(value);
    },
    handleDefaultUnitPriceBlur() {
      this.activeUnitPriceItemId = null;
    },
    normalizeSort(item) {
      item.sort_order = String(Math.max(1, Number(item.sort_order || 1)));
    },
    normalizeNumericString(value) {
      if (value === '' || value === null || typeof value === 'undefined') {
        return '';
      }

      return String(Number(value || 0));
    },
    formatCurrencyInput(value) {
      if (value === '' || value === null || typeof value === 'undefined') {
        return '';
      }

      return `Rp ${Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    displayDefaultUnitPrice(item) {
      if (this.activeUnitPriceItemId === item.id) {
        return this.typingUnitPriceValue(item.default_unit_price);
      }

      return this.formatCurrencyInput(item.default_unit_price);
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
    resequenceItems() {
      this.items = this.items.map((item, index) => ({
        ...item,
        sort_order: String(index + 1),
      }));
    },
    submitForm() {
      this.errorMessage = '';

      if (!this.form.work_type_id || !this.form.template_name) {
        this.errorMessage = 'Header template belum lengkap.';
        return;
      }

      if (this.items.length === 0) {
        this.errorMessage = 'Minimal harus ada satu item template.';
        return;
      }

      this.isSubmitting = true;

      const payload = {
        work_type_id: Number(this.form.work_type_id),
        template_name: this.form.template_name,
        description: this.form.description,
        is_active: this.form.is_active,
        items: this.items.map((item, index) => ({
          cost_item_id: item.cost_item_id,
          default_quantity: item.default_quantity === '' ? null : Number(item.default_quantity),
          default_duration: item.default_duration === '' ? null : Number(item.default_duration),
          default_unit_price: item.default_unit_price === '' ? null : Number(item.default_unit_price),
          sort_order: Number(item.sort_order || index + 1),
        })),
      };

      const request = this.isEditMode
        ? axios.put(`/templates/${this.$route.params.id}`, payload)
        : axios.post('/templates', payload);

      request.then((response) => {
        this.$toasted.show(response.data.message || 'Template berhasil disimpan.', { type: 'success' });
        this.$router.push('/app/admin/templates');
      }).catch((error) => {
        if (error && error.response && error.response.data) {
          const responseData = error.response.data;
          if (responseData.errors) {
            const firstErrorKey = Object.keys(responseData.errors)[0];
            this.errorMessage = responseData.errors[firstErrorKey];
          } else {
            this.errorMessage = responseData.message || 'Gagal menyimpan template.';
          }
        } else {
          this.errorMessage = 'Gagal menyimpan template.';
        }
      }).finally(() => {
        this.isSubmitting = false;
      });
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
    generateItemId() {
      const current = this.nextItemId;
      this.nextItemId += 1;
      return current;
    },
  },
  created() {
    this.loadReferences().then(() => this.loadTemplate());
  },
};
</script>

<style src="./Template.scss" lang="scss" scoped />
