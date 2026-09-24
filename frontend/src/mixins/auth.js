export function getCurrentUser() {
    try {
        return JSON.parse(localStorage.getItem('user') || '{}');
    } catch (error) {
        return {};
    }
}

export function isAuthenticated() {
    const user = getCurrentUser();
    return !!user.email;
}

export function isAdmin() {
    const user = getCurrentUser();
    return user.role === 'admin';
}

export const AuthMixin = {
    methods: {
        isAuthenticated,
        isAdmin,
        getCurrentUser
    }
};
