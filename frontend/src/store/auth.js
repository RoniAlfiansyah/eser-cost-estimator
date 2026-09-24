import axios from "axios";
import router from '../Routes';

export default {
    namespaced: true,
    state: {
        isFetching: false,
        errorMessage: ''
    },
    mutations: {
        LOGIN_FAILURE(state, payload) {
            state.isFetching = false;
            state.errorMessage = payload;
        },
        LOGIN_SUCCESS(state) {
            state.isFetching = false;
            state.errorMessage = '';
        },
        LOGIN_REQUEST(state) {
            state.isFetching = true;
        },
    },
    actions: {
        loginUser({dispatch}, creds) {
          dispatch('requestLogin');
          if (creds.social) {
            dispatch('loginError', 'Social login belum diaktifkan.');
          } else if (creds.email.length > 0 && creds.password.length > 0) {
            axios.post("/login", creds).then(res => {
              dispatch('receiveUser', res.data.user);
            }).catch(err => {
              const message = err && err.response && err.response.data && err.response.data.message
                ? err.response.data.message
                : 'Login gagal. Silakan coba lagi.';
              dispatch('loginError', message);
            })
          } else {
            dispatch('loginError', 'Something was wrong. Try again');
          }
        },
        receiveUser({dispatch}, user) {
          localStorage.setItem('user', JSON.stringify(user));
          dispatch('receiveLogin');
        },
        logoutUser() {
            axios.post('/logout').finally(() => {
              localStorage.removeItem('user');
              router.push('/login');
            });
        },
        loginError({commit}, payload) {
            commit('LOGIN_FAILURE', payload);
        },
        receiveLogin({commit}) {
            commit('LOGIN_SUCCESS');
            router.push('/app/main/analytics');
        },
        requestLogin({commit}) {
            commit('LOGIN_REQUEST');
        },
        checkSession({dispatch}) {
            return axios.get('/me')
              .then(res => {
                localStorage.setItem('user', JSON.stringify(res.data.user));
                dispatch('receiveLogin');
                return true;
              })
              .catch(() => {
                localStorage.removeItem('user');
                return false;
              });
        }
    },
};
