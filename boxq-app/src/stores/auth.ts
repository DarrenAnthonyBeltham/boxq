import { defineStore } from 'pinia';
import api from '../services/api';

interface User {
    id?: string;
    _id?: string;
    name: string;
    email: string;
    role: string;
    department: string;
    avatar?: string;
}

interface AuthState {
    user: User | null;
    token: string | null;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: JSON.parse(localStorage.getItem('user') || 'null'),
        token: localStorage.getItem('token') || null,
    }),
    
    getters: {
        isAuthenticated: (state) => !!state.token,
        currentUser: (state) => state.user,
        userRole: (state) => state.user?.role || 'employee',
    },
    
    actions: {
        setAuth(user: User, token: string) {
            this.user = user;
            this.token = token;
            localStorage.setItem('user', JSON.stringify(user));
            localStorage.setItem('token', token);
        },
        
        async logout() {
            try {
                if (this.token) {
                    await api.post('/logout').catch(() => {}); 
                }
            } finally {
                this.clearAuth();
            }
        },

        clearAuth() {
            this.user = null;
            this.token = null;
            localStorage.removeItem('user');
            localStorage.removeItem('token');
        },

        async checkAuth() {
            if (!this.token) return false;
            try {
                const response = await api.get('/user');
                this.user = response.data;
                localStorage.setItem('user', JSON.stringify(response.data));
                return true;
            } catch (error) {
                this.clearAuth();
                return false;
            }
        }
    }
});