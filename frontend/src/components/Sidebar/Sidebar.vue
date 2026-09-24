<template>
  <b-collapse :class="{ 'sidebar-collapse': true, 'sidebar-collapse-closed': !sidebarOpened }" id="sidebar-collapse" :visible="sidebarOpened">
    <nav :class="{ sidebar: true, 'sidebar-closed': !sidebarOpened }">
      <header class="logo">
        <router-link to="/app/main/analytics" class="logo-link">
          <img :src="activeLogo" alt="ESER Geosurvey" class="brand-logo">
          <span class="brand-name">ESER <span class="fw-semi-bold">Costing System</span></span>
        </router-link>
      </header>

      <ul class="nav">
        <NavLink
          :activeItem="activeItem"
          header="Planning & Scheduling"
          link="/app/scheduler/activities"
          iconName="flaticon-calendar"
          index="scheduler"
          :childrenLinks="schedulerLinks"
        />
        <NavLink
          :activeItem="activeItem"
          header="Dashboard"
          link="/app/main/analytics"
          iconName="flaticon-home"
          index="main/analytics"
          isHeader
        />
        <NavLink
          :activeItem="activeItem"
          header="Basic Cost"
          link="/app/basic-costs"
          iconName="flaticon-calculator"
          index="basic-costs"
          isHeader
        />
        <NavLink
          :activeItem="activeItem"
          header="Cost Proposal"
          link="/app/cost-proposals"
          iconName="flaticon-folder"
          index="cost-proposals"
          isHeader
        />
        <NavLink
          v-if="isAdminUser"
          :activeItem="activeItem"
          header="Master Data"
          link="/app/admin/master-data/categories"
          iconName="flaticon-list"
          index="admin/master-data"
          :childrenLinks="[
            { header: 'Kategori', link: '/app/admin/master-data/categories' },
            { header: 'Subkategori', link: '/app/admin/master-data/subcategories' },
            { header: 'Satuan', link: '/app/admin/master-data/units' },
            { header: 'Penandatangan', link: '/app/admin/master-data/signatories' },
          ]"
        />
        <NavLink
          v-if="isAdminUser"
          :activeItem="activeItem"
          header="Templates"
          link="/app/admin/templates"
          iconName="flaticon-folder"
          index="admin/templates"
          isHeader
        />
        <NavLink
          v-if="isAdminUser"
          :activeItem="activeItem"
          header="User Management"
          link="/app/admin/users"
          iconName="flaticon-user"
          index="admin/users"
          isHeader
        />
      </ul>

      <section class="sidebar-theme">
        <div class="sidebar-theme-title">Theme</div>
        <div class="sidebar-theme-subtitle">Pilih warna tampilan</div>
        <Colorpicker
          :colors="appConfig.themeColors"
          :activeColor="sidebarColorName"
          @change="updateLayoutComponentColor({ component: 'layoutComponent', color: $event })"
        />
      </section>
    </nav>
  </b-collapse>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import NavLink from './NavLink/NavLink';
import Colorpicker from '@/components/Colorpicker/Colorpicker';
import logoImage from '@/assets/company/eser-geosurvey-logo-white.png';
import logoImageDark from '@/assets/company/eser-geosurvey-logo.png';
import layoutMixin from '@/mixins/layout';

export default {
  name: 'Sidebar',
  components: { NavLink, Colorpicker },
  mixins: [layoutMixin],
  data() {
    return {
      currentUser: {},
      logoImage,
      logoImageDark,
    };
  },
  computed: {
    ...mapState('layout', {
      sidebarOpened: state => !state.sidebarClose,
      activeItem: state => state.sidebarActiveElement,
      sidebarColorName: state => state.sidebarColorName,
    }),
    activeLogo() {
      return this.sidebarColorName === 'white' ? this.logoImageDark : this.logoImage;
    },
    isAdminUser() {
      return this.currentUser.role === 'admin';
    },
    schedulerLinks() {
      const links = [
        { header: 'Ringkasan Proyek', link: '/app/scheduler/overview' },
        { header: 'Aktivitas & Gantt', link: '/app/scheduler/activities' },
        { header: 'Hubungan Aktivitas', link: '/app/scheduler/relationships' },
        { header: 'Personel & Peralatan', link: '/app/scheduler/resources' },
        { header: 'Kalender Kerja', link: '/app/scheduler/calendar' },
        { header: 'Baseline', link: '/app/scheduler/baselines' },
        { header: 'Laporan Schedule', link: '/app/scheduler/reports' },
      ];
      if (this.isAdminUser) {
        links.push(
          { header: 'Master Schedule', link: '/app/scheduler/master' },
          { header: 'Audit Schedule', link: '/app/scheduler/users' },
        );
      }
      return links;
    },
  },
  methods: {
    ...mapActions('layout', ['changeSidebarActive', 'updateLayoutComponentColor']),
    getStoredUser() {
      try {
        return JSON.parse(localStorage.getItem('user') || '{}');
      } catch (error) {
        return {};
      }
    },
    setActiveByRoute() {
      const paths = this.$route.fullPath.split('/');
      paths.pop();
      this.changeSidebarActive(paths.join('/'));
    },
  },
  created() {
    this.currentUser = this.getStoredUser();
    this.setActiveByRoute();
  },
  watch: {
    $route() {
      this.setActiveByRoute();
    },
  },
};
</script>

<style src="./Sidebar.scss" lang="scss" scoped />
