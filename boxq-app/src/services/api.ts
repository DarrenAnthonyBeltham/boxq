import axios from 'axios';
import { useToast } from 'vue-toastification';

const api = axios.create({
    baseURL: 'http://127.0.0.1:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

api.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        const toast = useToast();
        
        if (error.response) {
            if (error.response.status === 401) {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                
                if (window.location.pathname !== '/login') {
                    toast.error('Your session has expired. Please log in again.');
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 1000);
                }
            } 
            else if (error.response.status === 403) {
                toast.error('You do not have permission to perform this action.');
            }
            else if (error.response.status === 500) {
                toast.error('A critical server error occurred. Our team has been notified.');
            }
            else if (error.response.status === 429) {
                toast.warning('You are making too many requests. Please slow down.');
            }
        } else if (error.request) {
            toast.error('Network error. Please check your internet connection.');
        }

        return Promise.reject(error);
    }
);

export default api;