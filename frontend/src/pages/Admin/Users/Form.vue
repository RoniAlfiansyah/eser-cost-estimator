<template>
  <div class="users-page">
    <b-breadcrumb>
      <b-breadcrumb-item>ADMIN</b-breadcrumb-item>
      <b-breadcrumb-item to="/app/admin/users">Users</b-breadcrumb-item>
      <b-breadcrumb-item active>{{ isEditMode ? 'Edit User' : 'Tambah User' }}</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">{{ isEditMode ? 'Edit' : 'Tambah' }} <span class="fw-semi-bold">User</span></h2>
        <p class="page-subtitle">Kelola data user dan hak akses dari frontend Vue.</p>
      </div>
      <b-button variant="default" @click="$router.push('/app/admin/users')">
        Kembali
      </b-button>
    </div>

    <Widget title="<h5>Form <span class='fw-semi-bold'>User</span></h5>" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>
      <b-alert variant="success" :show="!!successMessage">{{ successMessage }}</b-alert>

      <b-form autocomplete="off" @submit.prevent="submitForm">
        <b-row>
          <b-col md="6">
            <b-form-group label="Nama">
              <b-form-input
                v-model="form.name"
                required
                autocomplete="off"
                placeholder="Masukkan nama user"
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group label="Email">
              <b-form-input
                v-model="form.email"
                type="email"
                required
                autocomplete="off"
                placeholder="Masukkan email user"
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="isEditMode ? 'Password Baru (opsional)' : 'Password'">
              <b-form-input
                v-model="form.password"
                type="password"
                :required="!isEditMode"
                autocomplete="new-password"
                :placeholder="isEditMode ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter'"
              />
            </b-form-group>
          </b-col>
          <b-col md="3">
            <b-form-group label="Role">
              <b-form-select v-model="form.role" :options="roleOptions" />
            </b-form-group>
          </b-col>
          <b-col md="3">
            <b-form-group label="Status">
              <b-form-select v-model="form.is_active" :options="statusOptions" />
            </b-form-group>
          </b-col>
        </b-row>

        <div class="form-actions">
          <b-button type="submit" variant="primary" :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : (isEditMode ? 'Update User' : 'Simpan User') }}
          </b-button>
          <b-button type="button" variant="inverse" class="ms-2" @click="$router.push('/app/admin/users')">
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

export default {
  name: 'AdminUsersForm',
  components: { Widget },
  data() {
    return {
      form: {
        name: '',
        email: '',
        password: '',
        role: 'staff',
        is_active: '1',
      },
      roleOptions: [
        { value: 'admin', text: 'Admin' },
        { value: 'manager', text: 'Manager' },
        { value: 'staff', text: 'Staff' },
      ],
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
    isEditMode() {
      return !!this.$route.params.id;
    },
  },
  methods: {
    loadUser() {
      if (!this.isEditMode) {
        return;
      }

      axios.get(`/users/${this.$route.params.id}`)
        .then((response) => {
          const user = response.data.data;
          this.form.name = user.name;
          this.form.email = user.email;
          this.form.role = user.role;
          this.form.is_active = user.is_active ? '1' : '0';
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal memuat data user.';
        });
    },
    submitForm() {
      this.isSubmitting = true;
      this.errorMessage = '';
      this.successMessage = '';

      const payload = {
        name: this.form.name,
        email: this.form.email,
        password: this.form.password,
        role: this.form.role,
        is_active: this.form.is_active,
      };

      const request = this.isEditMode
        ? axios.put(`/users/${this.$route.params.id}`, payload)
        : axios.post('/users', payload);

      request
        .then((response) => {
          this.successMessage = response.data.message || 'User berhasil disimpan.';
          setTimeout(() => {
            this.$router.push('/app/admin/users');
          }, 700);
        })
        .catch((error) => {
          if (error && error.response && error.response.data) {
            const payloadError = error.response.data;
            if (payloadError.errors) {
              const firstErrorKey = Object.keys(payloadError.errors)[0];
              this.errorMessage = payloadError.errors[firstErrorKey];
            } else {
              this.errorMessage = payloadError.message || 'Gagal menyimpan user.';
            }
          } else {
            this.errorMessage = 'Gagal menyimpan user.';
          }
        })
        .finally(() => {
          this.isSubmitting = false;
        });
    },
  },
  created() {
    this.loadUser();
  },
};
</script>

<style src="./Users.scss" lang="scss" scoped />
