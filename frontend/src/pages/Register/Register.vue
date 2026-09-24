<template>
  <div class="auth-page">
    <b-container>
      <div class="text-center mb-4">
        <img :src="logoImage" alt="ESER Geosurvey" style="width: 280px; max-width: 85%; height: auto;">
      </div>
      <Widget class="widget-auth mx-auto" title="<h3 class='mt-0'>Daftar Akun ESER Costing System</h3>" customHeader>
        <p class="widget-auth-info">
          Daftarkan akun Anda untuk mulai menggunakan ESER Costing System.
        </p>
        <form class="mt" @submit.prevent="register">
          <b-alert class="alert-sm" variant="danger" :show="!!errorMessage">
            {{errorMessage}}
          </b-alert>
          <b-form-group label="Email" label-for="email">
            <b-input-group>
              <b-input-group-text slot="prepend"><i class="la la-user text-white"></i></b-input-group-text>
              <input id="email"
                     ref="email"
                     class="form-control input-transparent pl-3"
                     type="email"
                     required
                     placeholder="Email"/>
            </b-input-group>
          </b-form-group>
          <b-form-group label="Password" label-for="password">
            <b-input-group>
              <b-input-group-text slot="prepend"><i class="la la-lock text-white"></i></b-input-group-text>
              <input id="password"
                     ref="password"
                     class="form-control input-transparent pl-3"
                     type="password"
                     required
                     placeholder="Password"/>
            </b-input-group>
          </b-form-group>
          <b-form-group label="Konfirmasi Password" label-for="confirmPassword">
            <b-input-group>
              <b-input-group-text slot="prepend"><i class="la la-lock text-white"></i></b-input-group-text>
              <input id="confirmPassword"
                     @blur="checkPassword"
                     ref="confirmPassword"
                     class="form-control input-transparent pl-3"
                     type="password"
                     required
                     placeholder="Konfirmasi Password"/>
            </b-input-group>
          </b-form-group>
          <div class="bg-widget-transparent auth-widget-footer">
            <b-button type="submit" variant="danger" class="auth-btn" size="sm">
              {{this.isFetching ? 'Loading...' : 'Daftar'}}
            </b-button>
            <p class="widget-auth-info mt-4">
              Sudah punya akun? Login sekarang.
            </p>
            <router-link class="d-block text-center mb-4" to="login">Masuk ke Akun</router-link>
            <div class="social-buttons">
              <b-button @click="this.googleLogin" variant="primary" class="social-button">
                <i class="social-icon social-google"></i>
                <p class="social-text">GOOGLE</p>
              </b-button>
              <b-button @click="this.microsoftLogin" variant="success" class="social-button">
                <i class="social-icon social-microsoft"></i>
                <p class="social-text">MICROSOFT</p>
              </b-button>
            </div>
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
  import Widget from '../../components/Widget/Widget';
  import {mapActions, mapState} from 'vuex';
  import logoImage from '@/assets/company/eser-geosurvey-logo-white.png';

  export default {
    name: 'RegisterPage',
    components: {Widget},
    data() {
      return {
        logoImage,
      };
    },
    computed: {
      ...mapState('register', {
        isFetching: state => state.isFetching,
        errorMessage: state => state.errorMessage,
      }),
    },
    methods: {
      ...mapActions('register', ['registerUser', 'registerError']),
      ...mapActions('auth', ['loginUser']),
      register() {
        const email = this.$refs.email.value;
        const password = this.$refs.password.value;

        if (!this.isPasswordValid()) {
          this.checkPassword();
        } else {
          this.registerUser({creds: {email, password}, $toasted: this.$toasted});
        }
      },
      googleLogin() {
        this.loginUser({social: "google"});
      },
      microsoftLogin() {
        this.loginUser({social: "microsoft"});
      },
      checkPassword() {
        if (!this.isPasswordValid()) {
          if (!this.$refs.password.value) {
            this.registerError("Password field is empty");
          } else {
            this.registerError("Passwords are not equal");
          }
          setTimeout(() => {
            this.registerError();
          }, 3 * 1000)
        }
      },
      isPasswordValid() {
        return this.$refs.password.value && this.$refs.password.value === this.$refs.confirmPassword.value;
      }
    },
  }
</script>
