// The Vue build version to load with the `import` command
// (runtime-only or standalone) has been set in webpack.base.conf with an alias.
import Vue from 'vue';
import BootstrapVue from 'bootstrap-vue';
import * as VueGoogleMaps from 'vue2-google-maps';
import VueTouch from 'vue-touch';
import Trend from 'vuetrend';
import { ClientTable } from 'vue-tables-2';
import VueTextareaAutosize from 'vue-textarea-autosize';
import mavonEditor from 'mavon-editor';
import { VueMaskDirective } from 'v-mask';
import VeeValidate from 'vee-validate';
import VueFormWizard from 'vue-form-wizard';
import axios from 'axios';
import Toasted from 'vue-toasted';
import VCalendar from 'v-calendar';
import VueApexCharts from 'vue-apexcharts';
import CKEditor from '@ckeditor/ckeditor5-vue';
import bFormSlider from 'vue-bootstrap-slider';
import { component as VueCodeHighlight } from 'vue-code-highlight';

import store from './store';
import router from './Routes';
import App from './App';
import layoutMixin from './mixins/layout';
import { AuthMixin } from './mixins/auth';
import config from './config';
import Widget from './components/Widget/Widget';
import Scrollspy from './documentation/pages/ScrollSpyComponent';

axios.defaults.baseURL = config.baseURLApi;
axios.defaults.headers.common['Content-Type'] = "application/json";
axios.defaults.withCredentials = true;

let authRedirectInProgress = false;

axios.interceptors.response.use(
  response => response,
  error => {
    const status = error && error.response ? error.response.status : null;
    const requestConfig = error && error.config ? error.config : {};
    const requestUrl = String(requestConfig.url || '');
    const isLoginRequest = /\/login\/?$/.test(requestUrl);
    const isProtectedPage = router.currentRoute.path.indexOf('/app') === 0;

    if (status === 401 && !requestConfig.skipAuthRedirect && !isLoginRequest && isProtectedPage) {
      localStorage.removeItem('user');

      if (!authRedirectInProgress && router.currentRoute.path !== '/login') {
        authRedirectInProgress = true;
        router.replace(
          { path: '/login', query: { reason: 'session-expired' } },
          () => { authRedirectInProgress = false; },
          () => { authRedirectInProgress = false; },
        );
      }
    }

    return Promise.reject(error);
  },
);

Vue.use(BootstrapVue);
Vue.use(VCalendar, {
  firstDayOfWeek: 2
});
Vue.use(VueTouch);
Vue.use(Trend);
Vue.component('vue-code-highlight', VueCodeHighlight);
Vue.component('Widget', Widget);
Vue.component('Scrollspy', Scrollspy);
Vue.use(bFormSlider);
Vue.use(VueGoogleMaps, {
  load: {
    key: 'AIzaSyB7OXmzfQYua_1LEhRdqsoYzyJOPh9hGLg',
  },
});
Vue.use(ClientTable, { theme: 'bootstrap4' });
Vue.use(VueTextareaAutosize);
Vue.use(CKEditor);
Vue.use(mavonEditor);
Vue.component('apexchart', VueApexCharts);
Vue.directive('mask', VueMaskDirective);
Vue.use(VeeValidate, { fieldsBagName: 'fieldsbag' });
Vue.use(VueFormWizard);
Vue.mixin(layoutMixin);
Vue.mixin(AuthMixin);
Vue.use(Toasted, {duration: 10000});

Vue.config.productionTip = false;

/* eslint-disable no-new */
new Vue({
  el: '#app',
  store,
  router,
  render: h => h(App),
});
