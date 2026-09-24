<template>
  <div class="users-page">
    <b-breadcrumb>
      <b-breadcrumb-item>ADMIN</b-breadcrumb-item>
      <b-breadcrumb-item active>Users</b-breadcrumb-item>
    </b-breadcrumb>

    <div class="page-header">
      <div>
        <h2 class="page-title">User <span class="fw-semi-bold">Management</span></h2>
        <p class="page-subtitle">Lihat pengguna yang tersimpan di database dan kelola aksesnya.</p>
      </div>
      <b-button variant="primary" @click="$router.push('/app/admin/users/create')">
        Tambah User
      </b-button>
    </div>

    <Widget title="<h5>Daftar <span class='fw-semi-bold'>User</span></h5>" customHeader>
      <b-alert variant="danger" :show="!!errorMessage">{{ errorMessage }}</b-alert>

      <div class="filter-bar">
        <b-row>
          <b-col md="8">
            <b-form-group label="Cari User">
              <b-form-input v-model="filters.q" placeholder="Cari nama, email, atau role user" />
            </b-form-group>
          </b-col>
          <b-col md="4" class="d-flex align-items-end">
            <div class="form-actions compact">
              <b-button variant="primary" @click="loadUsers">Cari</b-button>
              <b-button variant="inverse" @click="resetFilters">Reset</b-button>
            </div>
          </b-col>
        </b-row>
      </div>

      <div class="table-responsive">
        <table class="table table-hover users-table">
          <colgroup>
            <col class="col-id">
            <col class="col-name">
            <col class="col-email">
            <col class="col-role">
            <col class="col-status">
            <col class="col-login">
            <col class="col-actions">
          </colgroup>
          <thead>
            <tr>
              <th>ID</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="7" class="text-center text-muted py-4">Memuat data user...</td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="7" class="text-center text-muted py-4">Belum ada user di database.</td>
            </tr>
            <tr v-for="user in users" :key="user.id">
              <td>{{ user.id }}</td>
              <td>{{ user.name }}</td>
              <td>{{ user.email }}</td>
              <td>
                <span class="role-badge" :class="`role-${user.role}`">{{ user.role }}</span>
              </td>
              <td>
                <span class="status-badge" :class="user.is_active ? 'active' : 'inactive'">
                  {{ user.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>{{ user.last_login_at || '-' }}</td>
              <td>
                <div class="user-actions">
                  <b-button size="sm" variant="default" @click="$router.push(`/app/admin/users/${user.id}/edit`)">
                    Edit
                  </b-button>
                  <b-button size="sm" variant="danger" @click="deleteUser(user)">
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
  name: 'AdminUsersList',
  components: { Widget },
  data() {
    return {
      users: [],
      isLoading: false,
      errorMessage: '',
      filters: {
        q: '',
      },
    };
  },
  methods: {
    loadUsers() {
      this.isLoading = true;
      this.errorMessage = '';

      axios.get('/users', {
        params: this.filters.q ? { q: this.filters.q } : {},
      })
        .then((response) => {
          this.users = response.data.data || [];
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal memuat data user.';
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    deleteUser(user) {
      if (!window.confirm(`Hapus user ${user.name}?`)) {
        return;
      }

      axios.delete(`/users/${user.id}`)
        .then((response) => {
          this.$toasted.show(response.data.message || 'User berhasil dihapus.', { type: 'success' });
          this.loadUsers();
        })
        .catch((error) => {
          this.errorMessage = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : 'Gagal menghapus user.';
        });
    },
    resetFilters() {
      this.filters.q = '';
      this.loadUsers();
    },
  },
  created() {
    this.loadUsers();
  },
};
</script>

<style src="./Users.scss" lang="scss" scoped />
