<template>
  <b-navbar toggleable="md" class="app-header d-print-none">
    <button type="button" class="sidebarToggle" @click="switchSidebarMethod">
      <i class="la" :class="sidebarClose ? 'la-angle-right' : 'la-angle-left'" />
      <span class="d-sm-down-none">{{ sidebarClose ? 'Buka Menu' : 'Tutup Menu' }}</span>
    </button>

    <b-navbar-nav class="navbar-nav-mobile ms-auto">
      <b-nav-text class="me-3 d-sm-down-none">
        <b-alert class="header-alert" dismissible v-model="showNavbarAlert">
          <i class="fa fa-info-circle me-1"></i> Anda login sebagai {{ userRoleLabel }}.
        </b-alert>
      </b-nav-text>

      <b-nav-item-dropdown right menu-class="py-0">
        <template slot="button-content">
          <div class="headerUser">
            <span class="avatar rounded-circle thumb-sm">
              <img
                v-if="user.avatar"
                class="rounded-circle"
                :src="user.avatar"
                :alt="user.name || 'avatar'"
              />
              <span v-else>{{ firstUserLetter }}</span>
            </span>
            <div class="headerUserText">
              <strong>{{ user.name || 'User' }}</strong>
              <small>{{ user.email || '-' }}</small>
            </div>
            <span class="headerUserRole">{{ user.role || 'guest' }}</span>
          </div>
        </template>

        <b-dropdown-item to="/app/main/analytics">
          <i class="la la-home me-2" /> Dashboard
        </b-dropdown-item>
        <b-dropdown-item to="/app/basic-costs">
          <i class="la la-calculator me-2" /> Basic Cost
        </b-dropdown-item>
        <b-dropdown-item v-if="isAdminUser" to="/app/admin/master-data/categories">
          <i class="la la-database me-2" /> Master Data
        </b-dropdown-item>
        <b-dropdown-item v-if="isAdminUser" to="/app/admin/users">
          <i class="la la-users me-2" /> User Management
        </b-dropdown-item>
        <b-dropdown-divider />
        <b-dropdown-item-button @click="logoutUser">
          <i class="la la-sign-out me-2" /> Log Out
        </b-dropdown-item-button>
      </b-nav-item-dropdown>

    </b-navbar-nav>
  </b-navbar>
</template>

<script>
import { mapActions, mapState } from 'vuex';

export default {
  name: 'Header',
  data() {
    return {
      user: {},
      showNavbarAlert: true,
    };
  },
  computed: {
    ...mapState('layout', {
      sidebarClose: state => state.sidebarClose,
    }),
    firstUserLetter() {
      return (this.user.name || this.user.email || 'U')[0].toUpperCase();
    },
    userRoleLabel() {
      if (!this.user.role) {
        return 'pengguna';
      }

      return this.user.role.charAt(0).toUpperCase() + this.user.role.slice(1);
    },
    isAdminUser() {
      return this.user.role === 'admin';
    },
  },
  methods: {
    ...mapActions('layout', ['switchSidebar', 'changeSidebarActive']),
    ...mapActions('auth', ['logoutUser']),
    getStoredUser() {
      try {
        return JSON.parse(localStorage.getItem('user') || '{}');
      } catch (error) {
        return {};
      }
    },
    switchSidebarMethod() {
      if (!this.sidebarClose) {
        this.switchSidebar(true);
        this.changeSidebarActive(null);
      } else {
        this.switchSidebar(false);
        const paths = this.$route.fullPath.split('/');
        paths.pop();
        this.changeSidebarActive(paths.join('/'));
      }
    },
  },
  created() {
    this.user = this.getStoredUser();
  },
};
</script>

<style src="./Header.scss" lang="scss" />
