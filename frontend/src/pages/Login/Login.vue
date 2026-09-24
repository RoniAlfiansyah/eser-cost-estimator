<template>
  <div class="auth-page light-blue theme--dark">
    <b-container>
      <div class="text-center mb-4">
        <img :src="logoImage" alt="ESER Geosurvey" style="width: 280px; max-width: 85%; height: auto;">
      </div>
      <Widget class="widget-auth mx-auto" title="<h3 class='mt-0'>Login ESER Costing System</h3>" customHeader>
        <p class="widget-auth-info">
          Gunakan akun ESER Costing System untuk masuk.
        </p>
        <p class="widget-auth-info text-center mt-2">
          Gunakan email dan password yang aktif di database.
        </p>
        <form class="mt" @submit.prevent="login">
          <b-alert class="alert-sm" variant="danger" :show="!!errorMessage">
            {{errorMessage}}
          </b-alert>
          <b-form-group label="Email" label-for="email">
            <b-input-group class="mb-3">
              <b-input-group-text ><i class="la la-user text-white"></i></b-input-group-text>
              <input id="email"
                     ref="email"
                     class="form-control input-transparent pl-3"
                     type="email"
                     required
                     placeholder="Email"/>
            </b-input-group>
          </b-form-group>
          <b-form-group label="Password" label-for="password">
            <b-input-group class="mb-3">
              <b-input-group-text ><i class="la la-lock text-white"></i></b-input-group-text>
              <input id="password"
                     ref="password"
                     class="form-control input-transparent pl-3"
                     type="password"
                     required
                     placeholder="Password"/>
            </b-input-group>
          </b-form-group>
          <div class="bg-widget auth-widget-footer">
            <b-button type="submit" variant="danger" class="auth-btn" size="sm">
              <span class="auth-btn-circle">
                <i class="la la-caret-right"></i>
              </span>
              {{this.isFetching ? 'Loading...' : 'Login'}}
            </b-button>
            <p class="widget-auth-info mt-4">
              Belum punya akun?
            </p>
            <router-link class="d-block text-center mb-4" to="register">Daftar Akun</router-link>
          </div>
        </form>
      </Widget>
    </b-container>
    <footer class="auth-footer">
      ESER Costing System
    </footer>
  </div>
</template>

<script>
import Widget from '@/components/Widget/Widget';
import {mapState, mapActions} from 'vuex';
import logoImage from '@/assets/company/eser-geosurvey-logo-white.png';

import NavLink from '../../components/Sidebar/NavLink/NavLink';

export default {
  name: 'LoginPage',
  components: {NavLink, Widget },
  data() {
    return {
      logoImage,
    };
  },
  computed: {
    ...mapState('auth', {
      isFetching: state => state.isFetching,
      errorMessage: state => state.errorMessage,
    }),
  },
  methods: {
    ...mapActions('auth', ['loginUser', 'receiveLogin']),
    login() {
      const email = this.$refs.email.value;
      const password = this.$refs.password.value;

      if (email.length !== 0 && password.length !== 0) {
        this.loginUser({email, password});
      }
    },
    setTheme() {

      let theme = localStorage.getItem("theme")

      document.querySelector('body').setAttribute("class", `light-blue ${'theme--' + (theme ? theme : 'dark')}`)
    }
  },
  created() {

    if (this.isAuthenticated()) {
      this.receiveLogin();
    }
  },
  mounted() {
    this.setTheme()
  }
};
</script>
