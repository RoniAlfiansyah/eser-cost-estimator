<template>
<div :class="{ root: true, 'layout-sidebar-closed': sidebarClose }">
  <Header />
  <Sidebar />
  <div ref="content" class="content animated fadeInUp">
    <transition name="router-animation">
      <router-view />
    </transition>
  </div>
  <footer class="contentFooter">
    ESER Costing System
  </footer>
</div>
</template>

<script>
import { mapState } from 'vuex';

import Sidebar from '@/components/Sidebar/Sidebar';
import Header from '@/components/Header/Header';

import './Layout.scss';

export default {
  name: 'Layout',
  components: { Sidebar, Header },
  computed: {
    ...mapState('layout', ['sidebarClose']),
  },
  methods: {
    setTheme() {
      let theme = localStorage.getItem("theme")
      document.querySelector('body').setAttribute("class", `light-blue ${'theme--' + (theme || 'dark')}`)
    },
  },
  created() {
    this.setTheme()
  },
  mounted() {
    this.$refs.content.addEventListener('animationend', () => {
      this.$refs.content.classList.remove('animated');
      this.$refs.content.classList.remove('fadeInUp');
    });
  }
};
</script>

<style src="./Layout.scss" lang="scss" />
