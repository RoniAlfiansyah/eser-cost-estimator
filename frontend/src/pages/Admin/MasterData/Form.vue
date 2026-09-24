<template>
  <div class="master-data-page">
    <b-breadcrumb>
      <b-breadcrumb-item>MASTER DATA</b-breadcrumb-item>
      <b-breadcrumb-item :to="config.routeBase">{{ config.breadcrumb }}</b-breadcrumb-item>
      <b-breadcrumb-item active>{{ isEditMode ? `Edit ${config.singular}` : `Tambah ${config.singular}` }}</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">{{ isEditMode ? 'Edit' : 'Tambah' }} {{ config.singular }}</h2>
        <p class="page-subtitle">Masukkan data {{ config.singular.toLowerCase() }} yang akan dipakai pada proses costing.</p>
      </div>
      <b-button variant="default" @click="$router.push(config.routeBase)">
        Kembali
      </b-button>
    </div>

    <Widget :title="widgetTitle" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>
      <b-alert variant="success" :show="!!successMessage">{{ successMessage }}</b-alert>

      <b-form @submit.prevent="submitForm">
        <b-row>
          <template v-if="entityType === 'categories'">
            <b-col md="8">
              <b-form-group label="Nama Kategori">
                <b-form-input v-model="form.name" required placeholder="Contoh: Topography Survey" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Status">
                <b-form-select v-model="form.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'subcategories'">
            <b-col md="4">
              <b-form-group label="Kategori">
                <b-form-select v-model="form.category_id" :options="categoryOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Satuan">
                <b-form-select v-model="form.unit_id" :options="unitOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Nama Subkategori">
                <b-form-input v-model="form.name" required placeholder="Contoh: Field Work" />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Durasi Default">
                <b-form-input v-model="form.default_duration" type="number" step="0.01" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Harga Dasar">
                <b-form-input v-model="form.default_price" type="number" step="0.01" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Deskripsi">
                <b-form-textarea v-model="form.description" rows="3" placeholder="Catatan master subkategori" />
              </b-form-group>
            </b-col>
            <b-col md="2">
              <b-form-group label="Status">
                <b-form-select v-model="form.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'units'">
            <b-col md="5">
              <b-form-group label="Nama Satuan">
                <b-form-input v-model="form.name" required placeholder="Contoh: Day" />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Simbol">
                <b-form-input v-model="form.symbol" required placeholder="Contoh: day" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Status">
                <b-form-select v-model="form.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'costItems'">
            <b-col md="4">
              <b-form-group label="Kategori">
                <b-form-select v-model="form.category_id" :options="categoryOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Subkategori / Nama Item">
                <b-form-select v-model="form.subcategory_id" :options="filteredSubcategoryOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Satuan">
                <b-form-select v-model="form.unit_id" :options="unitOptions" required />
              </b-form-group>
            </b-col>
            <b-col md="3">
              <b-form-group label="Status">
                <b-form-select v-model="form.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
            <b-col md="5">
              <b-form-group label="Nama Item">
                <b-form-input :value="selectedSubcategoryName" readonly plaintext class="readonly-item-name" />
                <small class="text-muted">Nama item otomatis mengikuti subkategori yang dipilih.</small>
              </b-form-group>
            </b-col>
            <b-col md="2">
              <b-form-group label="Durasi Default">
                <b-form-input v-model="form.default_duration" type="number" step="0.01" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="2">
              <b-form-group label="Harga Dasar">
                <b-form-input v-model="form.default_price" type="number" step="0.01" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="9">
              <b-form-group label="Deskripsi">
                <b-form-textarea v-model="form.description" rows="4" placeholder="Catatan atau deskripsi item biaya" />
              </b-form-group>
            </b-col>
          </template>

          <template v-else-if="entityType === 'signatories'">
            <b-col md="5">
              <b-form-group label="Nama Penandatangan">
                <b-form-input v-model="form.name" required placeholder="Contoh: Rusli Dain" />
              </b-form-group>
            </b-col>
            <b-col md="4">
              <b-form-group label="Jabatan">
                <b-form-input v-model="form.position_title" required placeholder="Contoh: Direktur" />
              </b-form-group>
            </b-col>
            <b-col md="1">
              <b-form-group label="Urutan">
                <b-form-input v-model="form.sort_order" type="number" min="0" />
              </b-form-group>
            </b-col>
            <b-col md="2">
              <b-form-group label="Status">
                <b-form-select v-model="form.is_active" :options="statusOptions" />
              </b-form-group>
            </b-col>
            <b-col md="12">
              <b-form-group label="File Tanda Tangan (PNG)">
                <b-form-file
                  accept=".png,image/png"
                  placeholder="Pilih file PNG"
                  drop-placeholder="Drop file PNG di sini"
                  @input="onSignatureFileSelected"
                />
                <small class="text-muted d-block mt-2">Gunakan PNG dengan background transparan agar hasil proposal lebih rapi.</small>
              </b-form-group>
            </b-col>
            <b-col v-if="signaturePreviewUrl" md="12">
              <div class="signature-preview-box">
                <img :src="signaturePreviewUrl" alt="Preview tanda tangan" class="signature-preview-image">
                <div class="signature-preview-actions">
                  <b-button size="sm" variant="inverse" @click="clearSignatureImage">Hapus Gambar</b-button>
                </div>
              </div>
            </b-col>
          </template>
        </b-row>

        <div class="form-actions">
          <b-button type="submit" variant="primary" :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : (isEditMode ? `Update ${config.singular}` : `Simpan ${config.singular}`) }}
          </b-button>
          <b-button type="button" variant="inverse" class="ms-2" @click="$router.push(config.routeBase)">
            Batal
          </b-button>
        </div>
      </b-form>
    </Widget>
  </div>
</template>

<script>
import axios from 'axios';
import Widget from '@/components/Widget/Widget';
import { getMasterDataConfig } from './config';

export default {
  name: 'MasterDataForm',
  components: { Widget },
  data() {
    return {
      form: {
        name: '',
        category_id: '',
        symbol: '',
        subcategory_id: '',
        unit_id: '',
        default_duration: '0',
        default_price: '0',
        description: '',
        position_title: '',
        signature_image_base64: '',
        signature_image_url: '',
        remove_signature_image: false,
        sort_order: '0',
        is_active: '1',
      },
      categoryOptions: [{ value: '', text: 'Pilih Kategori' }],
      subcategoryOptions: [],
      unitOptions: [{ value: '', text: 'Pilih Satuan' }],
      statusOptions: [
        { value: '1', text: 'Active' },
        { value: '0', text: 'Inactive' },
      ],
      isSubmitting: false,
      errorMessage: '',
      successMessage: '',
    };
  },
  computed: {
    entityType() {
      return this.$route.meta.entityType;
    },
    config() {
      return getMasterDataConfig(this.entityType);
    },
    isEditMode() {
      return !!this.$route.params.id;
    },
    widgetTitle() {
      return `<h5>Form <span class='fw-semi-bold'>${this.config.singular}</span></h5>`;
    },
    filteredSubcategoryOptions() {
      const options = this.subcategoryOptions.filter((option) => (
        !this.form.category_id || option.categoryId === this.form.category_id
      )).map(({ value, text }) => ({ value, text }));

      return [{ value: '', text: 'Pilih Subkategori' }].concat(options);
    },
    selectedSubcategoryName() {
      const selected = this.subcategoryOptions.find((option) => option.value === this.form.subcategory_id);
      return selected ? selected.text.split(' - ').slice(1).join(' - ') : '-';
    },
    signaturePreviewUrl() {
      return this.form.signature_image_base64 || this.form.signature_image_url || '';
    },
  },
  methods: {
    loadReferences() {
      if (this.entityType === 'categories') {
        return Promise.resolve();
      }

      const requests = [];

      if (this.entityType === 'subcategories' || this.entityType === 'costItems') {
        requests.push(
          axios.get('/categories').then((response) => {
            this.categoryOptions = [{ value: '', text: 'Pilih Kategori' }].concat(
              (response.data.data || []).map((item) => ({
                value: String(item.id),
                text: item.name,
              })),
            );
          }),
        );
      }

      if (this.entityType === 'subcategories' || this.entityType === 'costItems') {
        requests.push(
          axios.get('/units').then((response) => {
            this.unitOptions = [{ value: '', text: 'Pilih Satuan' }].concat(
              (response.data.data || []).map((item) => ({
                value: String(item.id),
                text: `${item.name} (${item.symbol})`,
              })),
            );
          }),
        );
      }

      if (this.entityType === 'costItems') {
        requests.push(
          axios.get('/subcategories').then((response) => {
            this.subcategoryOptions = (response.data.data || []).map((item) => ({
              value: String(item.id),
              text: `${item.category_name} - ${item.name}`,
              categoryId: String(item.category_id),
            }));
          }),
        );
        requests.push(
          axios.get('/units').then((response) => {
            this.unitOptions = [{ value: '', text: 'Pilih Satuan' }].concat(
              (response.data.data || []).map((item) => ({
                value: String(item.id),
                text: `${item.name} (${item.symbol})`,
              })),
            );
          }),
        );
      }

      return Promise.all(requests);
    },
    loadRecord() {
      if (!this.isEditMode) {
        return;
      }

      axios.get(`${this.config.endpoint}/${this.$route.params.id}`)
        .then((response) => {
          const data = response.data.data;

          if (this.entityType === 'categories') {
            this.form.name = data.name;
            this.form.is_active = data.is_active ? '1' : '0';
            return;
          }

          if (this.entityType === 'subcategories') {
            this.form.category_id = String(data.category_id);
            this.form.unit_id = String(data.unit_id);
            this.form.name = data.name;
            this.form.default_duration = String(data.default_duration);
            this.form.default_price = String(data.default_price);
            this.form.description = data.description || '';
            this.form.is_active = data.is_active ? '1' : '0';
            return;
          }

          if (this.entityType === 'units') {
            this.form.name = data.name;
            this.form.symbol = data.symbol;
            this.form.is_active = data.is_active ? '1' : '0';
            return;
          }

          if (this.entityType === 'signatories') {
            this.form.name = data.name;
            this.form.position_title = data.position_title || '';
            this.form.signature_image_base64 = '';
            this.form.signature_image_url = data.signature_image_url || '';
            this.form.remove_signature_image = false;
            this.form.sort_order = data.sort_order != null ? String(data.sort_order) : '0';
            this.form.is_active = data.is_active ? '1' : '0';
            return;
          }

          this.form.category_id = String(data.category_id);
          this.form.subcategory_id = String(data.subcategory_id);
          this.form.unit_id = String(data.unit_id);
          this.form.default_duration = String(data.default_duration);
          this.form.default_price = String(data.default_price);
          this.form.description = data.description || '';
          this.form.is_active = data.is_active ? '1' : '0';
        })
        .catch((error) => {
          this.errorMessage = this.getErrorMessage(error, `Gagal memuat data ${this.config.singular.toLowerCase()}.`);
        });
    },
    submitForm() {
      this.isSubmitting = true;
      this.errorMessage = '';
      this.successMessage = '';

      const payload = this.buildPayload();
      const request = this.isEditMode
        ? axios.put(`${this.config.endpoint}/${this.$route.params.id}`, payload)
        : axios.post(this.config.endpoint, payload);

      request.then((response) => {
        this.successMessage = response.data.message || 'Data berhasil disimpan.';
        setTimeout(() => {
          this.$router.push(this.config.routeBase);
        }, 700);
      }).catch((error) => {
        if (error && error.response && error.response.data) {
          const responseData = error.response.data;
          if (responseData.errors) {
            const firstErrorKey = Object.keys(responseData.errors)[0];
            this.errorMessage = responseData.errors[firstErrorKey];
          } else {
            this.errorMessage = responseData.message || 'Gagal menyimpan data.';
          }
        } else {
          this.errorMessage = 'Gagal menyimpan data.';
        }
      }).finally(() => {
        this.isSubmitting = false;
      });
    },
    buildPayload() {
      if (this.entityType === 'categories') {
        return {
          name: this.form.name,
          is_active: this.form.is_active,
        };
      }

      if (this.entityType === 'subcategories') {
        return {
          category_id: this.form.category_id,
          unit_id: this.form.unit_id,
          name: this.form.name,
          default_duration: this.form.default_duration,
          default_price: this.form.default_price,
          description: this.form.description,
          is_active: this.form.is_active,
        };
      }

      if (this.entityType === 'units') {
        return {
          name: this.form.name,
          symbol: this.form.symbol,
          is_active: this.form.is_active,
        };
      }

      if (this.entityType === 'signatories') {
        return {
          name: this.form.name,
          position_title: this.form.position_title,
          signature_image_base64: this.form.signature_image_base64,
          remove_signature_image: this.form.remove_signature_image ? '1' : '0',
          sort_order: this.form.sort_order,
          is_active: this.form.is_active,
        };
      }

      return {
        category_id: this.form.category_id,
        subcategory_id: this.form.subcategory_id,
        unit_id: this.form.unit_id,
        default_duration: this.form.default_duration,
        default_price: this.form.default_price,
        description: this.form.description,
        is_active: this.form.is_active,
      };
    },
    getErrorMessage(error, fallback) {
      return error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : fallback;
    },
    onSignatureFileSelected(file) {
      if (!file) {
        return;
      }

      if (file.type !== 'image/png') {
        this.errorMessage = 'File tanda tangan harus berupa PNG.';
        return;
      }

      this.errorMessage = '';
      const reader = new FileReader();
      reader.onload = () => {
        this.form.signature_image_base64 = typeof reader.result === 'string' ? reader.result : '';
        this.form.signature_image_url = '';
        this.form.remove_signature_image = false;
      };
      reader.onerror = () => {
        this.errorMessage = 'Gagal membaca file tanda tangan.';
      };
      reader.readAsDataURL(file);
    },
    clearSignatureImage() {
      this.form.signature_image_base64 = '';
      this.form.signature_image_url = '';
      this.form.remove_signature_image = true;
    },
  },
  watch: {
    'form.category_id'() {
      if (this.entityType !== 'costItems') {
        return;
      }

      if (!this.filteredSubcategoryOptions.find((option) => option.value === this.form.subcategory_id)) {
        this.form.subcategory_id = '';
      }
    },
  },
  created() {
    this.loadReferences().then(() => {
      this.loadRecord();
    });
  },
};
</script>

<style src="./MasterData.scss" lang="scss" scoped />
