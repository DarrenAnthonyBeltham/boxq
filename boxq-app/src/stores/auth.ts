import { defineStore } from 'pinia';
import api from '../services/api';

export interface User {
    id?: string;
    name: string;
    email: string;
    role: string;
    department: string;
}

export interface LoginCredentials {
    email: string;
    password?: string;
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
    },
    actions: {
        async login(credentials: LoginCredentials) {
            const response = await api.post('/login', credentials);
            
            this.token = response.data.token;
            this.user = response.data.user;
            
            if (this.token) {
                localStorage.setItem('token', this.token);
            }
            if (this.user) {
                localStorage.setItem('user', JSON.stringify(this.user));
            }
        },
        async logout() {
            try {
                await api.post('/logout');
            } catch (error) {
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('token');
                localStorage.removeItem('user');
            }
        }
    }
});